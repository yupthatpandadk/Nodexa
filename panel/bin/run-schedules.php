<?php

use App\Models\Schedule;
use App\Services\DaemonClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function cronPartMatches(string $expr, int $value, int $min, int $max): bool
{
    $expr = trim($expr);
    if ($expr === '' || $expr === '*') return true;
    foreach (explode(',', $expr) as $piece) {
        $piece = trim($piece);
        if ($piece === '*') return true;
        if (preg_match('/^\*\/(\d+)$/', $piece, $m)) {
            $step = max(1, (int)$m[1]); if (($value - $min) % $step === 0) return true; continue;
        }
        if (preg_match('/^(\d+)-(\d+)(?:\/(\d+))?$/', $piece, $m)) {
            $start=max($min,(int)$m[1]);$end=min($max,(int)$m[2]);$step=max(1,(int)($m[3]??1));
            if ($value >= $start && $value <= $end && (($value-$start)%$step===0)) return true; continue;
        }
        if (ctype_digit($piece) && (int)$piece === $value) return true;
    }
    return false;
}

function scheduleMatches(Schedule $s, Carbon $time): bool
{
    return cronPartMatches((string)$s->cron_minute,$time->minute,0,59)
        && cronPartMatches((string)$s->cron_hour,$time->hour,0,23)
        && cronPartMatches((string)$s->cron_day_of_month,$time->day,1,31)
        && cronPartMatches((string)$s->cron_month,$time->month,1,12)
        && cronPartMatches((string)$s->cron_day_of_week,$time->dayOfWeek,0,6);
}

function nextRun(Schedule $s, Carbon $after): ?Carbon
{
    $candidate=$after->copy()->addMinute()->startOfMinute();
    for($i=0;$i<527040;$i++) { $local=$candidate->copy()->setTimezone($s->timezone ?: 'UTC'); if(scheduleMatches($s,$local)) return $candidate; $candidate->addMinute(); }
    return null;
}

$daemon=$app->make(DaemonClient::class);
$now=Carbon::now('UTC')->startOfMinute();
$schedules=Schedule::query()->where('enabled',true)->with(['tasks','server.node'])->get();
foreach($schedules as $schedule) {
    try {
        $local=$now->copy()->setTimezone($schedule->timezone ?: 'UTC');
        if(!scheduleMatches($schedule,$local)) { if(!$schedule->next_run_at || $schedule->next_run_at->lte($now)) $schedule->update(['next_run_at'=>nextRun($schedule,$now)]); continue; }
        if($schedule->last_run_at && $schedule->last_run_at->copy()->setTimezone('UTC')->startOfMinute()->equalTo($now)) continue;
        $server=$schedule->server;
        if(!$server || !$server->node) continue;
        if($schedule->only_when_online) {
            try { $stats=$daemon->stats($server); $state=strtolower((string)($stats['state']??'')); if(!in_array($state,['running','online'],true)) { $schedule->update(['next_run_at'=>nextRun($schedule,$now)]); continue; } }
            catch(Throwable) { $schedule->update(['next_run_at'=>nextRun($schedule,$now)]); continue; }
        }
        foreach($schedule->tasks as $task) {
            if((int)$task->time_offset>0) sleep(min((int)$task->time_offset,30));
            try {
                match($task->action) {
                    'command'=>$daemon->command($server,(string)$task->payload),
                    'power'=>$daemon->power($server,(string)$task->payload),
                    'backup'=>$daemon->backup($server,$task->payload ?: ('Scheduled backup '.Carbon::now()->format('Y-m-d H:i'))),
                    default=>null,
                };
            } catch(Throwable $e) { if(!$task->continue_on_failure) throw $e; }
        }
        $schedule->update(['last_run_at'=>Carbon::now('UTC'),'next_run_at'=>nextRun($schedule,$now)]);
        fwrite(STDOUT,"Ran schedule {$schedule->id} ({$schedule->name}) for server {$server->id}\n");
    } catch(Throwable $e) {
        fwrite(STDERR,"Schedule {$schedule->id} failed: {$e->getMessage()}\n");
        try{$schedule->update(['next_run_at'=>nextRun($schedule,$now)]);}catch(Throwable){}
    }
}
