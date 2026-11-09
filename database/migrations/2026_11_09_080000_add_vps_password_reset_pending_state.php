<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_flax_services')) {
            return;
        }

        if (!Schema::hasColumn('nodexa_flax_services', 'credential_reset_pending_at')) {
            Schema::table('nodexa_flax_services', function (Blueprint $table) {
                $table->timestamp('credential_reset_pending_at')->nullable();
            });
        }

        if (!Schema::hasColumn('nodexa_flax_services', 'credential_reset_previous_hash')) {
            Schema::table('nodexa_flax_services', function (Blueprint $table) {
                $table->char('credential_reset_previous_hash', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('nodexa_flax_services')) {
            return;
        }

        foreach (['credential_reset_pending_at', 'credential_reset_previous_hash'] as $column) {
            if (Schema::hasColumn('nodexa_flax_services', $column)) {
                Schema::table('nodexa_flax_services', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
