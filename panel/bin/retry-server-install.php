<?php

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identifier = trim((string) ($argv[1] ?? ''));
if ($identifier === '') {
    fwrite(STDERR, "Usage: php bin/retry-server-install.php <server identifier|uuid>\n");
    exit(1);
}

$server = Server::query()
    ->where('identifier', $identifier)
    ->orWhere('id', $identifier)
    ->orWhere('uuid', $identifier)
    ->first();

if (!$server) {
    fwrite(STDERR, "Server not found: {$identifier}\n");
    exit(1);
}

if (!in_array((string) $server->status, ['install_failed', 'installing'], true)) {
    fwrite(STDERR, "Server {$server->identifier} is {$server->status}; only failed/interrupted installs can be retried.\n");
    exit(1);
}

$server->update(['status' => 'installing']);

try {
    /** @var DaemonClient $daemon */
    $daemon = $app->make(DaemonClient::class);
    $daemon->createServer($server->load('node'));
    $server->update(['status' => 'offline']);
    fwrite(STDOUT, "Server {$server->identifier} provisioned successfully and is now offline/ready.\n");
    exit(0);
} catch (Throwable $e) {
    $server->update(['status' => 'install_failed']);
    fwrite(STDERR, "Provisioning failed for {$server->identifier}: {$e->getMessage()}\n");
    exit(2);
}
