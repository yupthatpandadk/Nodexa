<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_invoices')) {
            return;
        }

        if (!Schema::hasColumn('nodexa_invoices', 'cart_checkout_id')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('cart_checkout_id')->nullable()->index()->after('subscription_id');
            });
        }

        if (!Schema::hasColumn('nodexa_invoices', 'hidden_from_client')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->boolean('hidden_from_client')->default(false)->index()->after('cart_checkout_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('nodexa_invoices')) {
            return;
        }

        if (Schema::hasColumn('nodexa_invoices', 'hidden_from_client')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->dropColumn('hidden_from_client');
            });
        }

        if (Schema::hasColumn('nodexa_invoices', 'cart_checkout_id')) {
            Schema::table('nodexa_invoices', function (Blueprint $table) {
                $table->dropColumn('cart_checkout_id');
            });
        }
    }
};
