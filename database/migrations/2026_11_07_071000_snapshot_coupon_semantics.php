<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_cart_checkouts')) {
            if (!Schema::hasColumn('nodexa_cart_checkouts', 'coupon_type')) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                    $table->string('coupon_type', 20)->nullable()->after('coupon_code');
                });
            }

            if (!Schema::hasColumn('nodexa_cart_checkouts', 'coupon_value')) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                    $table->decimal('coupon_value', 12, 2)->nullable()->after('coupon_type');
                });
            }
        }

        if (Schema::hasTable('nodexa_subscriptions')) {
            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_type')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->string('discount_type', 20)->nullable()->after('discount_code');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_value')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_subscriptions')) {
            foreach (['discount_value','discount_type'] as $column) {
                if (Schema::hasColumn('nodexa_subscriptions', $column)) {
                    Schema::table('nodexa_subscriptions', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('nodexa_cart_checkouts')) {
            foreach (['coupon_value','coupon_type'] as $column) {
                if (Schema::hasColumn('nodexa_cart_checkouts', $column)) {
                    Schema::table('nodexa_cart_checkouts', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
