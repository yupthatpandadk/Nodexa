#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"

if [[ ! -f "$PANEL_DIR/artisan" || ! -f "$PANEL_DIR/vendor/autoload.php" ]]; then
  echo "[Nodexa] Minecraft runtime optimization skipped: panel runtime is not ready."
  exit 0
fi

cd "$PANEL_DIR"

RUN_AS=(php)
if id www-data >/dev/null 2>&1 && command -v sudo >/dev/null 2>&1; then
  RUN_AS=(sudo -u www-data php)
fi

"${RUN_AS[@]}" <<'PHP'
<?php
$base = getcwd();
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schema = Illuminate\Support\Facades\Schema::getFacadeRoot();
$db = Illuminate\Support\Facades\DB::getFacadeRoot();

$oldStandard = '-Xms128M -XX:MaxRAMPercentage=95.0';
$newStandard = '-Xms256M -XX:MaxRAMPercentage=75.0 -XX:+UseG1GC -XX:+ParallelRefProcEnabled -XX:+UseStringDeduplication';
$total = 0;

foreach (['servers', 'eggs'] as $table) {
    if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'startup')) {
        continue;
    }

    $rows = $db->table($table)
        ->where('startup', 'like', '%java%')
        ->where('startup', 'like', '%MaxRAMPercentage=95.0%')
        ->get(['id', 'startup']);

    foreach ($rows as $row) {
        $startup = (string) $row->startup;

        // Exact Nodexa/Pterodactyl default: apply the full low-idle optimization.
        if (str_contains($startup, $oldStandard)) {
            $optimized = str_replace($oldStandard, $newStandard, $startup);
        } else {
            // Custom Java command: preserve all custom flags, only reserve safer JVM headroom.
            $optimized = str_replace('-XX:MaxRAMPercentage=95.0', '-XX:MaxRAMPercentage=75.0', $startup);
        }

        if ($optimized !== $startup) {
            $db->table($table)->where('id', $row->id)->update(['startup' => $optimized]);
            $total++;
        }
    }
}

echo "[Nodexa] Minecraft/Java JVM defaults optimized on {$total} server/egg record(s).\n";
PHP
