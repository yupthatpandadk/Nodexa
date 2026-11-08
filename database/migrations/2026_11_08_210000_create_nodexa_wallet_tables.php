<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_wallets')) {
            Schema::create('nodexa_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->unique();
                $table->bigInteger('balance_cents')->default(0);
                $table->string('currency', 3)->default('DKK');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_wallet_transactions')) {
            Schema::create('nodexa_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wallet_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->bigInteger('amount_cents');
                $table->bigInteger('balance_after_cents');
                $table->string('currency', 3)->default('DKK');
                $table->string('type', 40)->index();
                $table->string('idempotency_key', 191)->unique();
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->timestamps();
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_wallet_transactions');
        Schema::dropIfExists('nodexa_wallets');
    }
};
