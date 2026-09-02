<?php

use App\Models\StorefrontSite;
use App\Services\ScheduleRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('nodexa:storefront-domains {--plain}', function () {
    if (!Schema::hasTable('storefront_sites')) {
        return 0;
    }

    $domains = StorefrontSite::query()->where('enabled', true)->get()->flatMap(function (StorefrontSite $site) {
        return array_merge([$site->primary_domain], $site->aliases ?? []);
    })->map([StorefrontSite::class, 'normalizeDomain'])->filter()->unique()->values();

    if ($this->option('plain')) {
        $this->line($domains->implode(' '));
    } else {
        $this->table(['Storefront domain'], $domains->map(fn ($domain) => [$domain])->all());
    }

    return 0;
})->purpose('List active Nodexa multisite storefront domains.');

Artisan::command('nodexa:schedules:run {--limit=50}', function (ScheduleRunner $runner) {
    $count = $runner->runDue(max(1, min(500, (int)$this->option('limit'))));
    $this->info("Processed {$count} due Nodexa schedule(s).");
    return 0;
})->purpose('Run due Nodexa game-server schedules.');

Schedule::command('nodexa:schedules:run --limit=100')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();
