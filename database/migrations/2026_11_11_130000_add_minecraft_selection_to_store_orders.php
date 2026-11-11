<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodexa_store_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('nodexa_store_orders', 'minecraft_egg_id')) {
                $table->unsignedInteger('minecraft_egg_id')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('nodexa_store_orders', 'minecraft_version')) {
                $table->string('minecraft_version', 32)->nullable()->after('minecraft_egg_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodexa_store_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('nodexa_store_orders', 'minecraft_version')) {
                $table->dropColumn('minecraft_version');
            }

            if (Schema::hasColumn('nodexa_store_orders', 'minecraft_egg_id')) {
                $table->dropColumn('minecraft_egg_id');
            }
        });
    }
};
