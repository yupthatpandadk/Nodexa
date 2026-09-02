<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\ScheduleTask;
use App\Services\DaemonClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScheduleTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 240;

    /** @param array<int,int> $taskIds */
    public function __construct(
        public int $scheduleId,
        public array $taskIds,
        public int $index,
    ) {}

    public function handle(DaemonClient $daemon): void
    {
        $taskId = $this->taskIds[$this->index] ?? null;
        if (!$taskId) return;

        $schedule = Schedule::query()->with('server.node')->find($this->scheduleId);
        $task = ScheduleTask::query()->find($taskId);
        if (!$schedule || !$task || (int) $task->schedule_id !== (int) $schedule->id) return;

        $server = $schedule->server;
        if (!$server || !$server->node) return;

        $continue = true;
        try {
            match ($task->action) {
                'command' => $daemon->command($server, (string) $task->payload),
                'power' => $daemon->power($server, (string) $task->payload),
                'backup' => $daemon->backup($server, $task->payload ?: ('Scheduled backup '.now()->format('Y-m-d H:i'))),
                default => throw new \RuntimeException('Unsupported schedule action: '.$task->action),
            };
        } catch (\Throwable $e) {
            $continue = (bool) $task->continue_on_failure;
            Log::log($continue ? 'warning' : 'error', 'Nodexa scheduled task failed', [
                'schedule_id' => $schedule->id,
                'task_id' => $task->id,
                'server_id' => $server->id,
                'continue_on_failure' => $continue,
                'error' => $e->getMessage(),
            ]);
        }

        if (!$continue) return;

        $nextIndex = $this->index + 1;
        $nextTaskId = $this->taskIds[$nextIndex] ?? null;
        if (!$nextTaskId) return;

        $next = ScheduleTask::query()->find($nextTaskId);
        if (!$next || (int) $next->schedule_id !== (int) $schedule->id) return;

        $job = self::dispatch($schedule->id, $this->taskIds, $nextIndex);
        if ((int) $next->time_offset > 0) {
            $job->delay(now()->addSeconds((int) $next->time_offset));
        }
    }
}
