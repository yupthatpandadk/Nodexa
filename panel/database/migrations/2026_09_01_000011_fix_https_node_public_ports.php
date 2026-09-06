<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodes')) {
            DB::table('nodes')
                ->where('scheme', 'https')
                ->where('daemon_port', 8080)
                ->update(['daemon_port' => 443]);
        }
    }

    public function down(): void
    {
        // No automatic rollback: a HTTPS Node may legitimately use port 443.
    }
};
