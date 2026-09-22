<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('nodexa_support_settings',function(Blueprint $t){$t->id();$t->string('key',100)->unique();$t->text('value')->nullable();$t->timestamps();});
  Schema::table('nodexa_support_tickets',function(Blueprint $t){$t->timestamp('resolved_at')->nullable();$t->timestamp('last_customer_reply_at')->nullable();$t->timestamp('last_staff_reply_at')->nullable();});
 }
 public function down(): void {Schema::table('nodexa_support_tickets',fn(Blueprint $t)=>$t->dropColumn(['resolved_at','last_customer_reply_at','last_staff_reply_at']));Schema::dropIfExists('nodexa_support_settings');}
};