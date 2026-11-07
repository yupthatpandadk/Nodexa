<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_cart_checkouts')) {
            Schema::create('nodexa_cart_checkouts', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->string('number', 80)->unique();
                $table->string('status', 40)->default('awaiting_payment')->index();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('currency', 8)->default('DKK');
                $table->string('payment_method', 80)->nullable();
                $table->string('payment_reference', 191)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_cart_checkout_items')) {
            Schema::create('nodexa_cart_checkout_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('checkout_id')->index();
                $table->string('type', 40)->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('name', 190);
                $table->text('description')->nullable();
                $table->decimal('unit_price', 12, 2);
                $table->string('currency', 8)->default('DKK');
                $table->longText('configuration')->nullable();
                $table->string('status', 40)->default('pending')->index();
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->timestamps();

                $table->foreign('checkout_id')
                    ->references('id')
                    ->on('nodexa_cart_checkouts')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_cart_checkout_items');
        Schema::dropIfExists('nodexa_cart_checkouts');
    }
};
