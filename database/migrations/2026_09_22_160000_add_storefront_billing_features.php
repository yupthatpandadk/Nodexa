<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('nodexa_store_orders', function(Blueprint $t) {
   $t->decimal('subtotal',10,2)->nullable()->after('amount');
   $t->decimal('discount',10,2)->default(0)->after('subtotal');
   $t->string('coupon_code')->nullable()->after('discount');
   $t->timestamp('cancelled_at')->nullable()->after('provisioned_at');
  });
  Schema::create('nodexa_store_coupons', function(Blueprint $t) {
   $t->id(); $t->string('code')->unique(); $t->string('type')->default('percent'); $t->decimal('value',10,2);
   $t->unsignedInteger('max_uses')->nullable(); $t->unsignedInteger('uses')->default(0); $t->timestamp('expires_at')->nullable();
   $t->boolean('enabled')->default(true); $t->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('nodexa_store_coupons');
  Schema::table('nodexa_store_orders', function(Blueprint $t){$t->dropColumn(['subtotal','discount','coupon_code','cancelled_at']);});
 }
};
