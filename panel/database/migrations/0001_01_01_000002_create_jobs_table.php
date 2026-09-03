<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Laravel 12 skeleton compatibility migration.
 *
 * Pterodactyl/Nodexa already defines its queue/job tables. Override Laravel's
 * default skeleton migration with a no-op to avoid duplicate table creation.
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
