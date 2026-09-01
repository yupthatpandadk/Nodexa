<?php

use App\Models\StorefrontSite;
use Illuminate\Support\Facades\Artisan;
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
