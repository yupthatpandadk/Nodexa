<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_store_sale_settings')) {
            Schema::create('nodexa_store_sale_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->decimal('percent', 5, 2)->default(0);
                $table->unsignedInteger('duration_months')->default(1);
                $table->string('label', 120)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nodexa_cart_checkouts')) {
            if (!Schema::hasColumn('nodexa_cart_checkouts', 'global_sale_percent')) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                    $table->decimal('global_sale_percent', 5, 2)->default(0)->after('coupon_duration_months');
                });
            }

            if (!Schema::hasColumn('nodexa_cart_checkouts', 'global_sale_discount')) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                    $table->decimal('global_sale_discount', 12, 2)->default(0)->after('global_sale_percent');
                });
            }

            if (!Schema::hasColumn('nodexa_cart_checkouts', 'global_sale_duration_months')) {
                Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                    $table->unsignedInteger('global_sale_duration_months')->default(0)->after('global_sale_discount');
                });
            }
        }

        if (Schema::hasTable('nodexa_subscriptions')) {
            if (!Schema::hasColumn('nodexa_subscriptions', 'sale_discount_amount')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->decimal('sale_discount_amount', 12, 2)->default(0)->after('amount');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'sale_months_total')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->unsignedInteger('sale_months_total')->default(0)->after('sale_discount_amount');
                });
            }

            if (!Schema::hasColumn('nodexa_subscriptions', 'sale_months_used')) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                    $table->unsignedInteger('sale_months_used')->default(0)->after('sale_months_total');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_subscriptions')) {
            foreach (['sale_months_used','sale_months_total','sale_discount_amount'] as $column) {
                if (Schema::hasColumn('nodexa_subscriptions', $column)) {
                    Schema::table('nodexa_subscriptions', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('nodexa_cart_checkouts')) {
            foreach (['global_sale_duration_months','global_sale_discount','global_sale_percent'] as $column) {
                if (Schema::hasColumn('nodexa_cart_checkouts', $column)) {
                    Schema::table('nodexa_cart_checkouts', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('nodexa_store_sale_settings');
    }
};
