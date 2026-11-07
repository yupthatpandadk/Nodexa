<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_store_coupons') && !Schema::hasColumn('nodexa_store_coupons', 'duration_months')) {
            Schema::table('nodexa_store_coupons', function (Blueprint $table) {
                $table->unsignedInteger('duration_months')->default(1)->after('value');
            });
        }

        if (Schema::hasTable('nodexa_cart_checkouts') && !Schema::hasColumn('nodexa_cart_checkouts', 'coupon_duration_months')) {
            Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                $table->unsignedInteger('coupon_duration_months')->nullable()->after('coupon_code');
            });
        }

        if (Schema::hasTable('nodexa_subscriptions')) {
            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_code')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->string('discount_code', 80)->nullable()->after('amount');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_amount')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_code');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_months_total')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->unsignedInteger('discount_months_total')->default(0)->after('discount_amount');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'discount_months_used')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->unsignedInteger('discount_months_used')->default(0)->after('discount_months_total');
                });
            }
        }

        if (Schema::hasTable('nodexa_invoices') && !Schema::hasColumn('nodexa_invoices', 'discount')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_invoices') && Schema::hasColumn('nodexa_invoices', 'discount')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->dropColumn('discount');
            });
        }

        if (Schema::hasTable('nodexa_subscriptions')) {
            foreach (['discount_months_used', 'discount_months_total', 'discount_amount', 'discount_code'] as $column) {
                if (Schema::hasColumn('nodexa_subscriptions', $column)) {
                    Schema::table('nodexa_subscriptions', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('nodexa_cart_checkouts') && Schema::hasColumn('nodexa_cart_checkouts', 'coupon_duration_months')) {
            Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                $table->dropColumn('coupon_duration_months');
            });
        }

        if (Schema::hasTable('nodexa_store_coupons') && Schema::hasColumn('nodexa_store_coupons', 'duration_months')) {
            Schema::table('nodexa_store_coupons', function (Blueprint $table) {
                $table->dropColumn('duration_months');
            });
        }
    }
};
