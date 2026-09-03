<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Laravel 12 skeleton compatibility migration.
 *
 * Nodexa/Pterodactyl manages cache storage independently of Laravel's default
 * skeleton migration. Override the skeleton migration with a no-op so fresh
 * installs do not create duplicate/incompatible cache tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
