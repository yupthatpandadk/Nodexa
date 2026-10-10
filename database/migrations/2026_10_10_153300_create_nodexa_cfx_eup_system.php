<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_subscriptions')) {
            Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('nodexa_subscriptions', 'service_type')) {
                    $table->string('service_type', 40)->nullable()->after('order_id')->index();
                }
                if (!Schema::hasColumn('nodexa_subscriptions', 'service_reference')) {
                    $table->unsignedBigInteger('service_reference')->nullable()->after('service_type')->index();
                }
                if (!Schema::hasColumn('nodexa_subscriptions', 'description')) {
                    $table->string('description', 255)->nullable()->after('service_reference');
                }
            });
        }

        if (!Schema::hasTable('nodexa_cfx_eup_keys')) {
            Schema::create('nodexa_cfx_eup_keys', function (Blueprint $table) {
                $table->id();
                $table->string('label', 120)->nullable();
                $table->longText('key_encrypted');
                $table->string('key_fingerprint', 64)->unique();
                $table->string('status', 30)->default('available')->index();
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_order_id')->nullable()->index();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_cfx_eup_orders')) {
            Schema::create('nodexa_cfx_eup_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedBigInteger('eup_key_id')->nullable()->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                $table->unsignedBigInteger('first_invoice_id')->nullable()->index();
                $table->unsignedInteger('created_by')->nullable()->index();
                $table->string('status', 30)->default('awaiting_payment')->index();
                $table->string('description', 180)->default('CFX EUP Key');
                $table->decimal('monthly_price', 12, 2)->default(0);
                $table->string('currency', 8)->default('DKK');
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_cfx_eup_orders');
        Schema::dropIfExists('nodexa_cfx_eup_keys');

        if (Schema::hasTable('nodexa_subscriptions')) {
            Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                foreach (['service_type', 'service_reference', 'description'] as $column) {
                    if (Schema::hasColumn('nodexa_subscriptions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
