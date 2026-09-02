<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Services\DaemonClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public int $scheduleId,
        public bool $manual = false,
    ) {}

    public function handle(DaemonClient $daemon): void
    {
        $schedule = Schedule::query()->with(['tasks', 'server.node'])->find($this->scheduleId);
        if (!$schedule || (!$schedule->enabled && !$this->manual)) return;

        $server = $schedule->server;
        if (!$server || !$server->node) {
            Log::warning('Nodexa schedule skipped because server/node is missing', ['schedule_id' => $this->scheduleId]);
            return;
        }

        if ($schedule->only_when_online) {
            try {
                $stats = $daemon->stats($server);
                $state = strtolower((string) ($stats['state'] ?? 'offline'));
                if (!in_array($state, ['online', 'running'], true)) return;
            } catch (\Throwable $e) {
                Log::warning('Nodexa schedule online check failed', [
                    'schedule_id' => $schedule->id,
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        $taskIds = $schedule->tasks->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($taskIds === []) return;

        $schedule->forceFill(['last_run_at' => now()])->save();
        $first = $schedule->tasks->first();
        $job = RunScheduleTaskJob::dispatch($schedule->id, $taskIds, 0);
        if ($first && (int) $first->time_offset > 0) {
            $job->delay(now()->addSeconds((int) $first->time_offset));
        }
    }
}
