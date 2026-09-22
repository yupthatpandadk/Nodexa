<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PDO;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class DatabaseBrowserController extends ClientApiController
{
    private function database(Server $server, Database $database): Database
    {
        abort_unless($database->server_id === $server->id, 404);
        $database->loadMissing('host');

        return $database;
    }

    private function pdo(Database $database): PDO
    {
        $host = $database->host;
        $password = $database->password;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host->host, $host->port, $database->database);

        return new PDO($dsn, $database->username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function identifier(string $value): string
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9_$-]+$/', $value), 422, 'Invalid database identifier.');
        return chr(96) . str_replace(chr(96), chr(96).chr(96), $value) . chr(96);
    }

    public function tables(Request $request, Server $server, Database $database): array
    {
        $database = $this->database($server, $database);
        $pdo = $this->pdo($database);
        $tables = $pdo->query('SHOW TABLE STATUS')->fetchAll();

        return ['data' => array_map(fn ($row) => [
            'name' => $row['Name'],
            'rows' => (int) ($row['Rows'] ?? 0),
            'engine' => $row['Engine'] ?? null,
            'collation' => $row['Collation'] ?? null,
        ], $tables)];
    }

    public function table(Request $request, Server $server, Database $database, string $table): array
    {
        $database = $this->database($server, $database);
        $pdo = $this->pdo($database);
        $tableSql = $this->identifier($table);
        $columns = $pdo->query("SHOW COLUMNS FROM {$tableSql}")->fetchAll();
        $primary = collect($columns)->firstWhere('Key', 'PRI')['Field'] ?? null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;
        $search = trim((string) $request->query('search', ''));

        $where = '';
        $params = [];
        if ($search !== '') {
            $parts = [];
            foreach ($columns as $column) {
                $parts[] = 'CAST('.$this->identifier($column['Field']).' AS CHAR) LIKE ?';
                $params[] = '%'.$search.'%';
            }
            $where = ' WHERE '.implode(' OR ', $parts);
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM {$tableSql}{$where}");
        $count->execute($params);
        $stmt = $pdo->prepare("SELECT * FROM {$tableSql}{$where} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'meta' => [
            'columns' => $columns, 'primary_key' => $primary, 'page' => $page,
            'per_page' => $perPage, 'total' => (int) $count->fetchColumn(),
        ]];
    }

    public function storeRow(Request $request, Server $server, Database $database, string $table): array
    {
        $database = $this->database($server, $database);
        $pdo = $this->pdo($database);
        $values = $request->input('values', []);
        abort_unless(is_array($values) && count($values), 422, 'No values supplied.');
        $columns = array_keys($values);
        $sql = 'INSERT INTO '.$this->identifier($table).' ('.implode(',', array_map([$this, 'identifier'], $columns)).') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')';
        $pdo->prepare($sql)->execute(array_values($values));
        return ['success' => true, 'insert_id' => $pdo->lastInsertId()];
    }

    public function updateRow(Request $request, Server $server, Database $database, string $table): array
    {
        $database = $this->database($server, $database);
        $pdo = $this->pdo($database);
        $key = (string) $request->input('key');
        $keyValue = $request->input('key_value');
        $values = $request->input('values', []);
        abort_unless($key !== '' && is_array($values) && count($values), 422);
        $sets = implode(',', array_map(fn ($c) => $this->identifier($c).' = ?', array_keys($values)));
        $sql = 'UPDATE '.$this->identifier($table).' SET '.$sets.' WHERE '.$this->identifier($key).' = ? LIMIT 1';
        $pdo->prepare($sql)->execute([...array_values($values), $keyValue]);
        return ['success' => true];
    }

    public function deleteRow(Request $request, Server $server, Database $database, string $table): array
    {
        $database = $this->database($server, $database);
        $pdo = $this->pdo($database);
        $key = (string) $request->input('key');
        abort_unless($key !== '', 422);
        $stmt = $pdo->prepare('DELETE FROM '.$this->identifier($table).' WHERE '.$this->identifier($key).' = ? LIMIT 1');
        $stmt->execute([$request->input('key_value')]);
        return ['success' => true];
    }
}
