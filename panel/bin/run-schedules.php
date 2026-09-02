<?php

use App\Services\ScheduleRunner;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * Backwards-compatible entry point for older Nodexa installations.
 * New installations invoke the same ScheduleRunner through the Artisan
 * nodexa:schedules:run command from nodexa-scheduler.timer.
 */
$runner = $app->make(ScheduleRunner::class);
$count = $runner->runDue(100);

fwrite(STDOUT, "Queued {$count} due Nodexa schedule(s).\n");
