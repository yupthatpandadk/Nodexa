<?php

namespace Pterodactyl\Console\Commands\Nodexa;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitorNodesCommand extends Command
{
    protected $signature = 'nodexa:monitor-nodes {--prune-days=30 : Number of days of health history to keep}';
    protected $description = 'Check Nodexa node connectivity and persist health history.';

    public function handle(): int
    {
        if (!Schema::hasTable('nodes') || !Schema::hasTable('nodexa_node_health_checks')) {
            $this->warn('Nodexa node monitoring tables are not ready. Run migrations first.');
            return self::SUCCESS;
        }

        $nodes = DB::table('nodes')->select('id', 'name', 'fqdn', 'daemonListen')->orderBy('id')->get();
        $now = now();

        foreach ($nodes as $node) {
            $host = trim((string) $node->fqdn);
            $port = (int) ($node->daemonListen ?: 8080);
            $reachable = false;
            $latency = null;
            $error = null;

            if ($host === '') {
                $error = 'Missing FQDN';
            } else {
                $started = microtime(true);
                $errno = 0;
                $errstr = '';
                $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 1.0, STREAM_CLIENT_CONNECT);
                if (is_resource($socket)) {
                    $reachable = true;
                    $latency = (int) round((microtime(true) - $started) * 1000);
                    fclose($socket);
                } else {
                    $error = trim($errstr) !== '' ? trim($errstr) : ('Connection failed' . ($errno ? ' (' . $errno . ')' : ''));
                }
            }

            DB::table('nodexa_node_health_checks')->insert([
                'node_id' => $node->id,
                'reachable' => $reachable,
                'latency_ms' => $latency,
                'error' => $error ? mb_substr($error, 0, 255) : null,
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->line(sprintf('%s: %s%s', $node->name, $reachable ? 'reachable' : 'unreachable', $latency !== null ? ' (' . $latency . ' ms)' : ''));
        }

        $days = max(1, (int) $this->option('prune-days'));
        DB::table('nodexa_node_health_checks')->where('checked_at', '<', now()->subDays($days))->delete();

        return self::SUCCESS;
    }
}
