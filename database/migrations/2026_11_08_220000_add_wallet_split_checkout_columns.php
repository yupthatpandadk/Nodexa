<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_cart_checkouts')) {
            return;
        }

        if (!Schema::hasColumn('nodexa_cart_checkouts', 'wallet_applied_cents')) {
            Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_applied_cents')->default(0);
            });
        }

        if (!Schema::hasColumn('nodexa_cart_checkouts', 'wallet_hold_state')) {
            Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                $table->string('wallet_hold_state', 24)->default('none');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('nodexa_cart_checkouts')) {
            return;
        }

        foreach (['wallet_hold_state', 'wallet_applied_cents'] as $column) {
            if (Schema::hasColumn('nodexa_cart_checkouts', $column)) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
