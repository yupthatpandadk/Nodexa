<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('nodexa_store_orders', function(Blueprint $t) {
   $t->unsignedInteger('server_id')->nullable()->after('product_id');
   $t->string('server_name')->nullable()->after('currency');
   $t->string('payment_method')->nullable()->after('server_name');
   $t->string('payment_reference')->nullable()->after('payment_method');
   $t->timestamp('paid_at')->nullable();
   $t->timestamp('provisioned_at')->nullable();
   $t->index('server_id');
  });
 }
 public function down(): void {
  Schema::table('nodexa_store_orders', function(Blueprint $t) {
   $t->dropIndex(['server_id']);
   $t->dropColumn(['server_id','server_name','payment_method','payment_reference','paid_at','provisioned_at']);
  });
 }
};
