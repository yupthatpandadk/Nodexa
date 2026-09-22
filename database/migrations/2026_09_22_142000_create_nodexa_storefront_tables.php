<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('nodexa_store_products',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->string('game');$t->text('description')->nullable();$t->decimal('price_monthly',10,2);$t->unsignedInteger('egg_id');$t->unsignedInteger('memory');$t->unsignedInteger('disk');$t->unsignedInteger('cpu');$t->unsignedInteger('databases')->default(0);$t->unsignedInteger('backups')->default(0);$t->unsignedInteger('allocations')->default(1);$t->boolean('enabled')->default(true);$t->boolean('featured')->default(false);$t->timestamps();});
  Schema::create('nodexa_store_orders',function(Blueprint $t){$t->id();$t->unsignedInteger('user_id');$t->unsignedBigInteger('product_id');$t->string('status')->default('pending');$t->decimal('amount',10,2);$t->string('currency',3)->default('DKK');$t->timestamps();$t->index(['user_id','status']);});
 }
 public function down(): void {Schema::dropIfExists('nodexa_store_orders');Schema::dropIfExists('nodexa_store_products');}
};
