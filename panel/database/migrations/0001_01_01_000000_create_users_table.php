<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Laravel 12 skeleton compatibility migration.
 *
 * Pterodactyl/Nodexa ships a MySQL schema dump that already contains the
 * users table. The fresh Laravel skeleton also ships a migration with this
 * filename, so we intentionally override it with a no-op to prevent the
 * installer from trying to create the table a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty: provided by Pterodactyl/Nodexa schema.
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
