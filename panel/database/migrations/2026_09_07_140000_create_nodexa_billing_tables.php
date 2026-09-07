<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_products')) Schema::create('billing_products', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->decimal('price',10,2); $t->string('currency',3)->default('DKK'); $t->string('billing_cycle')->default('monthly'); $t->boolean('active')->default(true); $t->json('provisioning')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('billing_orders')) Schema::create('billing_orders', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('product_id')->nullable()->index(); $t->string('status')->default('pending')->index(); $t->decimal('amount',10,2); $t->string('currency',3)->default('DKK'); $t->string('payment_method')->nullable(); $t->string('payment_reference')->nullable()->index(); $t->timestamps();
        });
        if (!Schema::hasTable('invoices')) Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('number')->unique(); $t->string('status')->default('unpaid')->index(); $t->decimal('subtotal',10,2); $t->decimal('tax',10,2)->default(0); $t->decimal('total',10,2); $t->string('currency',3)->default('DKK'); $t->timestamp('due_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('invoice_items')) Schema::create('invoice_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('invoice_id')->index(); $t->string('description'); $t->decimal('quantity',10,2)->default(1); $t->decimal('unit_price',10,2); $t->decimal('total',10,2); $t->timestamps();
        });
        if (!Schema::hasTable('billing_transactions')) Schema::create('billing_transactions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->unsignedBigInteger('invoice_id')->nullable()->index(); $t->string('gateway'); $t->string('gateway_id')->nullable()->index(); $t->string('status')->default('pending')->index(); $t->decimal('amount',10,2); $t->string('currency',3)->default('DKK'); $t->json('meta')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('tickets')) Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->string('subject'); $t->string('department')->default('support'); $t->string('priority')->default('normal'); $t->string('status')->default('open')->index(); $t->timestamps();
        });
        if (!Schema::hasTable('ticket_messages')) Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('ticket_id')->index(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->text('message'); $t->boolean('staff')->default(false); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['ticket_messages','tickets','billing_transactions','invoice_items','invoices','billing_orders','billing_products'] as $table) Schema::dropIfExists($table);
    }
};
