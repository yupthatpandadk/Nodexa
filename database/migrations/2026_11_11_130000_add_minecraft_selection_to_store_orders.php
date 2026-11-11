<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_store_orders')) {
            return;
        }

        $needsEggId = !Schema::hasColumn('nodexa_store_orders', 'minecraft_egg_id');
        $needsVersion = !Schema::hasColumn('nodexa_store_orders', 'minecraft_version');

        if (!$needsEggId && !$needsVersion) {
            return;
        }

        Schema::table('nodexa_store_orders', function (Blueprint $table) use ($needsEggId, $needsVersion): void {
            if ($needsEggId) {
                $table->unsignedInteger('minecraft_egg_id')->nullable()->after('product_id');
            }
            if ($needsVersion) {
                $table->string('minecraft_version', 32)->nullable()->after('minecraft_egg_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('nodexa_store_orders')) {
            return;
        }

        $hasVersion = Schema::hasColumn('nodexa_store_orders', 'minecraft_version');
        $hasEggId = Schema::hasColumn('nodexa_store_orders', 'minecraft_egg_id');

        if (!$hasVersion && !$hasEggId) {
            return;
        }

        Schema::table('nodexa_store_orders', function (Blueprint $table) use ($hasVersion, $hasEggId): void {
            if ($hasVersion) {
                $table->dropColumn('minecraft_version');
            }
            if ($hasEggId) {
                $table->dropColumn('minecraft_egg_id');
            }
        });
    }
};
