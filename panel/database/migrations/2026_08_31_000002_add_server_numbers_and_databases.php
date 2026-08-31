<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('servers', function(Blueprint $t){
   $t->unsignedBigInteger('server_number')->nullable()->unique()->after('uuid');
   $t->string('identifier',32)->nullable()->unique()->after('server_number');
  });
  Schema::create('server_databases', function(Blueprint $t){
   $t->id();
   $t->uuid('server_id');
   $t->string('name',64)->unique();
   $t->string('username',32)->unique();
   $t->text('password');
   $t->string('host')->default('127.0.0.1');
   $t->unsignedSmallInteger('port')->default(3306);
   $t->timestamps();
   $t->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
  });
 }
 public function down(): void {
  Schema::dropIfExists('server_databases');
  Schema::table('servers', function(Blueprint $t){$t->dropColumn(['server_number','identifier']);});
 }
};
