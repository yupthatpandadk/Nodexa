<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Allocations existed in the original core schema. New installations
        // therefore only need the extra parity fields, while older databases
        // that do not have the table can still create the complete schema.
        if (!Schema::hasTable('allocations')) {
            Schema::create('allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('node_id')->constrained()->cascadeOnDelete();
                $table->uuid('server_id')->nullable()->index();
                $table->string('ip', 255);
                $table->unsignedInteger('port');
                $table->string('alias')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->unique(['node_id', 'ip', 'port']);
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            });
            return;
        }

        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'notes')) $table->text('notes')->nullable();
            if (!Schema::hasColumn('allocations', 'is_primary')) $table->boolean('is_primary')->default(false);
        });
    }

    public function down(): void
    {
        // Do not drop the allocations table because it belongs to the core
        // schema. Only remove fields introduced by this upgrade migration.
        if (!Schema::hasTable('allocations')) return;
        Schema::table('allocations', function (Blueprint $table) {
            $drop=[];
            if (Schema::hasColumn('allocations','notes')) $drop[]='notes';
            if (Schema::hasColumn('allocations','is_primary')) $drop[]='is_primary';
            if ($drop) $table->dropColumn($drop);
        });
    }
};
