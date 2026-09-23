<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  // Pterodactyl uses unsigned integer primary keys for users/servers.
  // Keep FK column types identical or MariaDB rejects the constraints (errno 150).
  Schema::create('nodexa_support_tickets', function(Blueprint $t){
   $t->id();
   $t->string('ticket_number',24)->unique();
   $t->unsignedInteger('user_id');
   $t->unsignedInteger('server_id')->nullable();
   $t->string('subject',160);
   $t->string('department',40)->default('support');
   $t->string('priority',20)->default('normal');
   $t->string('status',30)->default('open');
   $t->timestamp('last_reply_at')->nullable();
   $t->timestamp('closed_at')->nullable();
   $t->timestamps();
   $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
   $t->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
  });
  Schema::create('nodexa_support_ticket_messages', function(Blueprint $t){
   $t->id();
   $t->unsignedBigInteger('ticket_id');
   $t->unsignedInteger('user_id')->nullable();
   $t->boolean('staff')->default(false);
   $t->longText('message');
   $t->timestamps();
   $t->foreign('ticket_id')->references('id')->on('nodexa_support_tickets')->cascadeOnDelete();
   $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
  });
 }
 public function down(): void {
  Schema::dropIfExists('nodexa_support_ticket_messages');
  Schema::dropIfExists('nodexa_support_tickets');
 }
};