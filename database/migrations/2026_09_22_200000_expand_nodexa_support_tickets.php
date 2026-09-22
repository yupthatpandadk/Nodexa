<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('nodexa_support_tickets',function(Blueprint $t){$t->unsignedBigInteger('assigned_to')->nullable()->after('user_id');$t->string('tags',500)->nullable();$t->timestamp('first_response_at')->nullable();$t->timestamp('due_at')->nullable();$t->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();});
  Schema::table('nodexa_support_ticket_messages',function(Blueprint $t){$t->boolean('internal')->default(false)->after('staff');});
  Schema::create('nodexa_support_ticket_attachments',function(Blueprint $t){$t->id();$t->unsignedBigInteger('ticket_id');$t->unsignedBigInteger('message_id')->nullable();$t->unsignedBigInteger('user_id')->nullable();$t->string('disk',30)->default('local');$t->string('path');$t->string('original_name');$t->string('mime',120)->nullable();$t->unsignedBigInteger('size')->default(0);$t->timestamps();$t->foreign('ticket_id')->references('id')->on('nodexa_support_tickets')->cascadeOnDelete();$t->foreign('message_id')->references('id')->on('nodexa_support_ticket_messages')->cascadeOnDelete();$t->foreign('user_id')->references('id')->on('users')->nullOnDelete();});
  Schema::create('nodexa_support_ticket_events',function(Blueprint $t){$t->id();$t->unsignedBigInteger('ticket_id');$t->unsignedBigInteger('user_id')->nullable();$t->string('event',60);$t->text('details')->nullable();$t->timestamps();$t->foreign('ticket_id')->references('id')->on('nodexa_support_tickets')->cascadeOnDelete();$t->foreign('user_id')->references('id')->on('users')->nullOnDelete();});
 }
 public function down(): void {Schema::dropIfExists('nodexa_support_ticket_events');Schema::dropIfExists('nodexa_support_ticket_attachments');Schema::table('nodexa_support_ticket_messages',fn(Blueprint $t)=>$t->dropColumn('internal'));Schema::table('nodexa_support_tickets',function(Blueprint $t){$t->dropForeign(['assigned_to']);$t->dropColumn(['assigned_to','tags','first_response_at','due_at']);});}
};