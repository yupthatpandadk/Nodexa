<?php
namespace App\Services;

use App\Models\DatabaseHost;
use PDO;
use RuntimeException;

class DatabaseProvisioner
{
    private function pdo(DatabaseHost $host): PDO
    {
        $ssl = $host->ssl ? ';sslmode=required' : '';
        return new PDO("mysql:host={$host->host};port={$host->port};charset=utf8mb4{$ssl}", $host->username, $host->plainPassword(), [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>5,
        ]);
    }

    private function quote(PDO $pdo, string $value): string { return $pdo->quote($value); }
    private function identifier(string $value): string { return '`'.str_replace('`', '``', $value).'`'; }

    public function test(DatabaseHost $host): string
    {
        $row = $this->pdo($host)->query('SELECT VERSION() AS version')->fetch();
        return (string)($row['version'] ?? 'unknown');
    }

    public function create(DatabaseHost $host, string $database, string $username, string $password): void
    {
        if (!preg_match('/^s\d+_[A-Za-z0-9_-]+$/', $database)) throw new RuntimeException('Invalid Nodexa database name.');
        if (!preg_match('/^u\d+_[A-Za-z0-9]{8}$/', $username)) throw new RuntimeException('Invalid Nodexa database username.');
        $pdo = $this->pdo($host);
        $remote = $host->remote_host ?: '%';
        $pdo->exec('CREATE DATABASE '.$this->identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $account = $this->quote($pdo,$username).'@'.$this->quote($pdo,$remote);
            $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$this->quote($pdo,$password));
            $pdo->exec('GRANT ALL PRIVILEGES ON '.$this->identifier($database).'.* TO '.$account);
        } catch (\Throwable $e) {
            $pdo->exec('DROP DATABASE IF EXISTS '.$this->identifier($database));
            try { $pdo->exec('DROP USER IF EXISTS '.$this->quote($pdo,$username).'@'.$this->quote($pdo,$remote)); } catch (\Throwable) {}
            throw $e;
        }
    }

    public function rotatePassword(DatabaseHost $host, string $username, string $password): void
    {
        if (!preg_match('/^u\d+_[A-Za-z0-9]{8}$/', $username)) throw new RuntimeException('Invalid Nodexa database username.');
        $pdo = $this->pdo($host);
        $remote = $host->remote_host ?: '%';
        $account = $this->quote($pdo,$username).'@'.$this->quote($pdo,$remote);
        $pdo->exec('ALTER USER '.$account.' IDENTIFIED BY '.$this->quote($pdo,$password));
    }

    public function delete(DatabaseHost $host, string $database, string $username): void
    {
        $pdo = $this->pdo($host);
        $remote = $host->remote_host ?: '%';
        $pdo->exec('DROP DATABASE IF EXISTS '.$this->identifier($database));
        $pdo->exec('DROP USER IF EXISTS '.$this->quote($pdo,$username).'@'.$this->quote($pdo,$remote));
    }
}
