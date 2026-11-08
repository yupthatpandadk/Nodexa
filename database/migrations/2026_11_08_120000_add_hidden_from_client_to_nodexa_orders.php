<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['nodexa_cart_checkouts', 'nodexa_store_orders'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'hidden_from_client')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('hidden_from_client')->default(false)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['nodexa_cart_checkouts', 'nodexa_store_orders'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'hidden_from_client')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('hidden_from_client');
                });
            }
        }
    }
};
