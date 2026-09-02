<?php

namespace App\Services;

use App\Jobs\RunScheduleJob;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleRunner
{
    public function runDue(int $limit = 50): int
    {
        $now = now();
        $ids = Schedule::query()
            ->where('enabled', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->orderByRaw('COALESCE(next_run_at, created_at) asc')
            ->limit(max(1, min(500, $limit)))
            ->pluck('id');

        $queued = 0;
        foreach ($ids as $id) {
            try {
                $claimed = DB::transaction(function () use ($id, $now) {
                    $schedule = Schedule::query()->lockForUpdate()->find($id);
                    if (!$schedule || !$schedule->enabled) return false;
                    if ($schedule->next_run_at && $schedule->next_run_at->isAfter($now)) return false;

                    $schedule->forceFill([
                        'next_run_at' => $this->nextRun($schedule, CarbonImmutable::instance($now)),
                    ])->save();

                    return true;
                });

                if (!$claimed) continue;
                RunScheduleJob::dispatch((int) $id, false);
                $queued++;
            } catch (\Throwable $e) {
                Log::error('Nodexa schedule could not be queued', [
                    'schedule_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    public function run(Schedule $schedule): void
    {
        RunScheduleJob::dispatch((int) $schedule->id, true);
    }

    public function nextRun(Schedule $schedule, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = $schedule->timezone ?: config('app.timezone', 'UTC');
        $cursor = ($from ?: CarbonImmutable::now($timezone))
            ->setTimezone($timezone)
            ->addMinute()
            ->startOfMinute();

        for ($i = 0; $i < 525600; $i++, $cursor = $cursor->addMinute()) {
            if ($this->matches($schedule, $cursor)) return $cursor->utc();
        }

        throw new \RuntimeException('Could not calculate next schedule run within one year.');
    }

    private function matches(Schedule $schedule, CarbonImmutable $date): bool
    {
        return $this->field($schedule->cron_minute, $date->minute, 0, 59)
            && $this->field($schedule->cron_hour, $date->hour, 0, 23)
            && $this->field($schedule->cron_day_of_month, $date->day, 1, 31)
            && $this->field($schedule->cron_month, $date->month, 1, 12)
            && $this->field($schedule->cron_day_of_week, $date->dayOfWeek, 0, 6);
    }

    private function field(?string $expression, int $value, int $min, int $max): bool
    {
        $expression = trim($expression ?: '*');
        foreach (explode(',', $expression) as $part) {
            $part = trim($part);
            if ($part === '*') return true;

            $step = 1;
            if (str_contains($part, '/')) {
                [$part, $rawStep] = explode('/', $part, 2);
                $step = max(1, (int) $rawStep);
            }

            if ($part === '*') {
                if (($value - $min) % $step === 0) return true;
                continue;
            }

            if (str_contains($part, '-')) {
                [$start, $end] = array_map('intval', explode('-', $part, 2));
                $start = max($min, $start);
                $end = min($max, $end);
                if ($value >= $start && $value <= $end && (($value - $start) % $step === 0)) return true;
                continue;
            }

            if (ctype_digit(ltrim($part, '+')) && (int) $part === $value) return true;
        }

        return false;
    }
}
