<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseProvisioner
{
    private function quote(string $value): string { return "'".str_replace(["\\", "'"], ["\\\\", "''"], $value)."'"; }
    private function identifier(string $value): string { return '`'.str_replace('`', '``', $value).'`'; }

    public function create(string $database, string $username, string $password): void
    {
        $admin = DB::connection(config('database.nodexa_admin_connection', 'mysql'));
        $admin->unprepared('CREATE DATABASE '.$this->identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $admin->unprepared('CREATE USER '.$this->quote($username)."@'%' IDENTIFIED BY ".$this->quote($password));
            $admin->unprepared('GRANT ALL PRIVILEGES ON '.$this->identifier($database).'.* TO '.$this->quote($username)."@'%'");
        } catch (\Throwable $e) {
            $admin->unprepared('DROP DATABASE IF EXISTS '.$this->identifier($database));
            throw $e;
        }
    }

    public function delete(string $database, string $username): void
    {
        $admin = DB::connection(config('database.nodexa_admin_connection', 'mysql'));
        $admin->unprepared('DROP DATABASE IF EXISTS '.$this->identifier($database));
        $admin->unprepared('DROP USER IF EXISTS '.$this->quote($username)."@'%'");
    }
}
