<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('gateway', 40)->unique();
                $table->string('name', 80);
                $table->boolean('enabled')->default(false)->index();
                $table->boolean('test_mode')->default(true);
                $table->text('public_key')->nullable();
                $table->text('secret_key')->nullable();
                $table->text('webhook_secret')->nullable();
                $table->text('merchant_id')->nullable();
                $table->text('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
