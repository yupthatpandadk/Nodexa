<?php
namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class ScheduleRunner
{
    public function __construct(private DaemonClient $daemon) {}

    public function runDue(int $limit = 50): int
    {
        $now = now();
        $schedules = Schedule::query()->with(['tasks','server.node'])
            ->where('enabled', true)
            ->where(function ($q) use ($now) { $q->whereNull('next_run_at')->orWhere('next_run_at','<=',$now); })
            ->orderByRaw('COALESCE(next_run_at, created_at) asc')->limit($limit)->get();
        $ran = 0;
        foreach ($schedules as $schedule) {
            try { $this->run($schedule); $ran++; }
            catch (\Throwable $e) { Log::error('Nodexa schedule failed',['schedule_id'=>$schedule->id,'server_id'=>$schedule->server_id,'error'=>$e->getMessage()]); }
        }
        return $ran;
    }

    public function run(Schedule $schedule): void
    {
        $schedule->loadMissing(['tasks','server.node']);
        $server = $schedule->server;
        if (!$server || !$server->node) throw new \RuntimeException('Schedule server or node is missing.');

        if ($schedule->only_when_online) {
            $stats = $this->daemon->stats($server);
            $state = strtolower((string)($stats['state'] ?? 'offline'));
            if (!in_array($state,['online','running'],true)) {
                $schedule->update(['next_run_at'=>$this->nextRun($schedule)]);
                return;
            }
        }

        foreach ($schedule->tasks as $task) {
            if ((int)$task->time_offset > 0) sleep(min((int)$task->time_offset, 300));
            try {
                match ($task->action) {
                    'command' => $this->daemon->command($server, (string)$task->payload),
                    'power' => $this->daemon->power($server, (string)$task->payload),
                    'backup' => $this->daemon->backup($server, $task->payload ?: ('Scheduled backup '.now()->format('Y-m-d H:i'))),
                    default => throw new \RuntimeException('Unsupported schedule action: '.$task->action),
                };
            } catch (\Throwable $e) {
                if (!$task->continue_on_failure) throw $e;
                Log::warning('Nodexa schedule task failed but continued',['schedule_id'=>$schedule->id,'task_id'=>$task->id,'error'=>$e->getMessage()]);
            }
        }
        $schedule->update(['last_run_at'=>now(),'next_run_at'=>$this->nextRun($schedule)]);
    }

    public function nextRun(Schedule $schedule, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $tz = $schedule->timezone ?: config('app.timezone','UTC');
        $cursor = ($from ?: CarbonImmutable::now($tz))->addMinute()->startOfMinute();
        for ($i=0; $i<525600; $i++, $cursor=$cursor->addMinute()) {
            if ($this->matches($schedule,$cursor)) return $cursor->utc();
        }
        throw new \RuntimeException('Could not calculate next schedule run within one year.');
    }

    private function matches(Schedule $s, CarbonImmutable $d): bool
    {
        return $this->field($s->cron_minute,$d->minute,0,59)
            && $this->field($s->cron_hour,$d->hour,0,23)
            && $this->field($s->cron_day_of_month,$d->day,1,31)
            && $this->field($s->cron_month,$d->month,1,12)
            && $this->field($s->cron_day_of_week,$d->dayOfWeek,0,6);
    }

    private function field(?string $expr, int $value, int $min, int $max): bool
    {
        $expr = trim($expr ?: '*');
        foreach (explode(',',$expr) as $part) {
            $part=trim($part); if ($part==='*') return true;
            $step=1; if (str_contains($part,'/')) { [$part,$raw]=explode('/',$part,2); $step=max(1,(int)$raw); }
            if ($part==='*') { if (($value-$min)%$step===0) return true; continue; }
            if (str_contains($part,'-')) { [$a,$b]=array_map('intval',explode('-',$part,2)); if ($value >= $a && $value <= $b && (($value-$a)%$step===0)) return true; continue; }
            if (ctype_digit(ltrim($part,'+')) && (int)$part === $value) return true;
        }
        return false;
    }
}
