<?php
namespace App\Services;

use PDO;
use RuntimeException;

class DatabaseProvisioner
{
    private function pdo(): PDO
    {
        $host = env('NODEXA_DB_ADMIN_HOST', '127.0.0.1');
        $port = (int) env('NODEXA_DB_ADMIN_PORT', 3306);
        $user = env('NODEXA_DB_ADMIN_USER', 'nodexa_dbadmin');
        $pass = env('NODEXA_DB_ADMIN_PASSWORD');
        if (!$pass) throw new RuntimeException('NODEXA_DB_ADMIN_PASSWORD is not configured.');
        return new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
    }

    private function quote(PDO $pdo, string $value): string { return $pdo->quote($value); }
    private function identifier(string $value): string { return '`'.str_replace('`', '``', $value).'`'; }

    public function create(string $database, string $username, string $password): void
    {
        if (!preg_match('/^s\d+_[A-Za-z0-9_-]+$/', $database)) throw new RuntimeException('Invalid Nodexa database name.');
        if (!preg_match('/^u\d+_[A-Za-z0-9]{8}$/', $username)) throw new RuntimeException('Invalid Nodexa database username.');
        $pdo = $this->pdo();
        $pdo->exec('CREATE DATABASE '.$this->identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $pdo->exec('CREATE USER '.$this->quote($pdo,$username)."@'%' IDENTIFIED BY ".$this->quote($pdo,$password));
            $pdo->exec('GRANT ALL PRIVILEGES ON '.$this->identifier($database).'.* TO '.$this->quote($pdo,$username)."@'%'");
        } catch (\Throwable $e) {
            $pdo->exec('DROP DATABASE IF EXISTS '.$this->identifier($database));
            try { $pdo->exec('DROP USER IF EXISTS '.$this->quote($pdo,$username)."@'%'"); } catch (\Throwable) {}
            throw $e;
        }
    }

    public function delete(string $database, string $username): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DROP DATABASE IF EXISTS '.$this->identifier($database));
        $pdo->exec('DROP USER IF EXISTS '.$this->quote($pdo,$username)."@'%'");
    }
}
