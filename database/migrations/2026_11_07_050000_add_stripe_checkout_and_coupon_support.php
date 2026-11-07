<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_stripe_settings')) {
            Schema::create('nodexa_stripe_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->longText('secret_key_encrypted')->nullable();
                $table->longText('webhook_secret_encrypted')->nullable();
                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_status', 40)->nullable();
                $table->text('last_test_message')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_stripe_events')) {
            Schema::create('nodexa_stripe_events', function (Blueprint $table) {
                $table->id();
                $table->string('stripe_event_id', 191)->unique();
                $table->string('type', 120)->index();
                $table->unsignedBigInteger('checkout_id')->nullable()->index();
                $table->string('status', 40)->default('processing')->index();
                $table->text('error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('nodexa_cart_checkouts')) {
            Schema::table('nodexa_cart_checkouts', function (Blueprint $table) {
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'coupon_id')) {
                    $table->unsignedBigInteger('coupon_id')->nullable()->index()->after('user_id');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'coupon_code')) {
                    $table->string('coupon_code', 80)->nullable()->after('coupon_id');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'coupon_reserved_at')) {
                    $table->timestamp('coupon_reserved_at')->nullable()->after('coupon_code');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_attempt')) {
                    $table->unsignedInteger('stripe_attempt')->default(0)->after('payment_reference');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_session_id')) {
                    $table->string('stripe_session_id', 191)->nullable()->unique()->after('stripe_attempt');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_payment_intent_id')) {
                    $table->string('stripe_payment_intent_id', 191)->nullable()->index()->after('stripe_session_id');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_status')) {
                    $table->string('stripe_status', 40)->nullable()->index()->after('stripe_payment_intent_id');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_amount_total')) {
                    $table->unsignedBigInteger('stripe_amount_total')->nullable()->after('stripe_status');
                }
                if (!Schema::hasColumn('nodexa_cart_checkouts', 'stripe_currency')) {
                    $table->string('stripe_currency', 8)->nullable()->after('stripe_amount_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_cart_checkouts')) {
            foreach ([
                'stripe_currency',
                'stripe_amount_total',
                'stripe_status',
                'stripe_payment_intent_id',
                'stripe_session_id',
                'stripe_attempt',
                'coupon_reserved_at',
                'coupon_code',
                'coupon_id',
            ] as $column) {
                if (Schema::hasColumn('nodexa_cart_checkouts', $column)) {
                    Schema::table('nodexa_cart_checkouts', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('nodexa_stripe_events');
        Schema::dropIfExists('nodexa_stripe_settings');
    }
};
