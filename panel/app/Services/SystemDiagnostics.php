<?php
namespace App\Services;

use App\Models\DatabaseHost;
use App\Models\SystemIssue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Throwable;

final class SystemDiagnostics
{
    public function scan(): array
    {
        $checks = [];
        $checks[] = $this->checkDatabase();
        $checks[] = $this->checkRedis();
        $checks[] = $this->checkStorage();
        $checks[] = $this->checkDisk();
        $checks[] = $this->checkService('nodexa-queue', 'queue', 'Queue worker');
        $checks[] = $this->checkService('nginx', 'nginx', 'Nginx');
        $checks[] = $this->checkService('mariadb', 'mariadb', 'MariaDB');
        $checks[] = $this->checkService('redis-server', 'redis_service', 'Redis service');
        $checks[] = $this->checkService('docker', 'docker', 'Docker');

        foreach (DatabaseHost::query()->where('enabled', true)->get() as $host) {
            $checks[] = $this->checkDatabaseHost($host);
        }

        return [
            'healthy' => collect($checks)->every(fn ($c) => ($c['status'] ?? '') === 'ok'),
            'checks' => $checks,
            'checked_at' => now(),
        ];
    }

    private function ok(string $key, string $label, array $extra = []): array
    {
        $this->resolve('system', $key);
        return array_merge(['key'=>$key,'label'=>$label,'status'=>'ok'], $extra);
    }

    private function fail(string $key, string $label, string $message, string $diagnosis, string $recommendation, string $severity = 'error', array $extra = []): array
    {
        SystemIssue::report(
            source: 'system',
            title: $label.' har en fejl',
            message: $message,
            severity: $severity,
            type: $key,
            context: array_merge([
                'diagnosis'=>$diagnosis,
                'recommendation'=>$recommendation,
            ], $extra)
        );

        return array_merge(['key'=>$key,'label'=>$label,'status'=>'error','message'=>$message,'diagnosis'=>$diagnosis,'recommendation'=>$recommendation], $extra);
    }

    private function resolve(string $source, string $type): void
    {
        SystemIssue::where('source', $source)->where('type', $type)->where('status', 'open')->get()
            ->each(fn (SystemIssue $issue) => $issue->resolveIssue());
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            return $this->ok('panel_database', 'Panel database', ['latency_ms'=>(int)round((microtime(true)-$start)*1000)]);
        } catch (Throwable $e) {
            return $this->fail('panel_database', 'Panel database', $e->getMessage(), 'Nodexa kan ikke forbinde til sin egen MySQL/MariaDB database.', 'Kontroller MariaDB, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME og DB_PASSWORD i panelets .env.', 'critical', ['exception'=>get_class($e)]);
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            Redis::connection()->ping();
            return $this->ok('redis_connection', 'Redis', ['latency_ms'=>(int)round((microtime(true)-$start)*1000)]);
        } catch (Throwable $e) {
            return $this->fail('redis_connection', 'Redis', $e->getMessage(), 'Panelets Redis-kø kan ikke kontaktes. Almindelige sidevisninger bruger lokal session/cache, men baggrundsjobs og planlagte handlinger kan fejle.', 'Kontroller redis-server og REDIS_HOST/REDIS_PORT i .env.', 'critical', ['exception'=>get_class($e)]);
        }
    }

    private function checkStorage(): array
    {
        $paths = [storage_path(), storage_path('logs'), storage_path('framework'), base_path('bootstrap/cache')];
        $bad = array_values(array_filter($paths, fn ($p) => !is_dir($p) || !is_writable($p)));
        if (!$bad) return $this->ok('panel_permissions', 'Panel filrettigheder');
        return $this->fail('panel_permissions', 'Panel filrettigheder', 'Nodexa kan ikke skrive til: '.implode(', ', $bad), 'Laravel mangler skriveadgang til en eller flere nødvendige mapper.', 'Kør chown -R www-data:www-data storage bootstrap/cache og giv mapperne skriveadgang.', 'critical', ['paths'=>$bad]);
    }

    private function checkDisk(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!$total || $free === false) return $this->fail('panel_disk', 'Diskplads', 'Diskforbrug kunne ikke aflæses.', 'PHP kunne ikke læse diskstatistik for paneldisken.', 'Kontroller mount, filsystem og PHP-rettigheder.', 'warning');
        $usedPct = round((1 - ($free / $total)) * 100, 1);
        if ($usedPct >= 95) return $this->fail('panel_disk', 'Diskplads', "Disken er {$usedPct}% fuld.", 'Kritisk lav ledig diskplads kan få databaser, logs, backups og opdateringer til at fejle.', 'Frigør diskplads straks eller udvid disken.', 'critical', ['used_percent'=>$usedPct,'free_bytes'=>(int)$free,'total_bytes'=>(int)$total]);
        if ($usedPct >= 85) return $this->fail('panel_disk', 'Diskplads', "Disken er {$usedPct}% fuld.", 'Nodexa nærmer sig lav diskplads.', 'Ryd gamle logs/backups eller udvid disken.', 'warning', ['used_percent'=>$usedPct,'free_bytes'=>(int)$free,'total_bytes'=>(int)$total]);
        return $this->ok('panel_disk', 'Diskplads', ['used_percent'=>$usedPct,'free_bytes'=>(int)$free,'total_bytes'=>(int)$total]);
    }

    private function checkService(string $service, string $key, string $label): array
    {
        try {
            $p = new Process(['systemctl','is-active',$service]);
            $p->setTimeout(4);
            $p->run();
            $state = trim($p->getOutput()) ?: trim($p->getErrorOutput());
            if ($p->isSuccessful() && $state === 'active') return $this->ok('service_'.$key, $label, ['service'=>$service,'state'=>$state]);
            return $this->fail('service_'.$key, $label, $state ?: 'Service er ikke aktiv.', "Systemd-servicen {$service} er ikke aktiv.", "Kør systemctl status {$service} og journalctl -u {$service} -n 100 for den konkrete årsag.", in_array($key,['queue','mariadb','redis_service','docker'], true) ? 'critical' : 'error', ['service'=>$service,'state'=>$state]);
        } catch (Throwable $e) {
            return $this->fail('service_'.$key, $label, $e->getMessage(), "Nodexa kunne ikke kontrollere systemd-servicen {$service}.", 'Kontroller at systemctl findes og at webbrugeren må læse service-status.', 'warning', ['service'=>$service,'exception'=>get_class($e)]);
        }
    }

    private function checkDatabaseHost(DatabaseHost $host): array
    {
        $key = 'database_host_'.$host->id;
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host->host, $host->port);
        $start = microtime(true);
        try {
            $options = [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT=>4];
            if ($host->ssl && defined('PDO::MYSQL_ATTR_SSL_CA')) $options[\PDO::MYSQL_ATTR_SSL_CA] = config('database.connections.mysql.options.' . \PDO::MYSQL_ATTR_SSL_CA);
            // DatabaseHost encrypts its administrator password at rest. Using
            // the model attribute directly sends the ciphertext to MySQL and
            // falsely marks a healthy host as offline.
            new \PDO($dsn, $host->username, $host->plainPassword(), $options);
            $this->resolve('database-host', $key);
            return ['key'=>$key,'label'=>'Database Host: '.$host->name,'status'=>'ok','latency_ms'=>(int)round((microtime(true)-$start)*1000),'database_host_id'=>$host->id];
        } catch (Throwable $e) {
            $raw = strtolower($e->getMessage());
            $diagnosis = 'Forbindelsen til Database Host fejlede.';
            $recommendation = 'Kontroller host, port, admin-bruger, password, firewall og MySQL bind-address.';
            if (str_contains($raw, 'access denied')) { $diagnosis = 'MySQL afviste loginoplysningerne til Database Host.'; $recommendation = 'Kontroller admin-brugernavn/password og allowed host for MySQL-brugeren.'; }
            elseif (str_contains($raw, 'connection refused')) { $diagnosis = 'Database-serveren kan nås, men MySQL lytter ikke på den valgte port.'; $recommendation = 'Kontroller MariaDB/MySQL service, port og bind-address.'; }
            elseif (str_contains($raw, 'timed out') || str_contains($raw, 'timeout')) { $diagnosis = 'Database Host svarede ikke inden timeout.'; $recommendation = 'Kontroller netværk, firewall og om database-serveren er online.'; }
            SystemIssue::report('database-host', 'Database Host '.$host->name.' fejler', $e->getMessage(), 'critical', $key, null, null, ['database_host_id'=>$host->id,'host'=>$host->host,'port'=>$host->port,'diagnosis'=>$diagnosis,'recommendation'=>$recommendation,'exception'=>get_class($e)]);
            return ['key'=>$key,'label'=>'Database Host: '.$host->name,'status'=>'error','message'=>$e->getMessage(),'diagnosis'=>$diagnosis,'recommendation'=>$recommendation,'database_host_id'=>$host->id];
        }
    }
}
