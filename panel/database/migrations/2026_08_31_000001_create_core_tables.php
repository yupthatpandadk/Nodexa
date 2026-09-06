<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('nodes', function(Blueprint $t){$t->id();$t->string('name');$t->string('fqdn');$t->string('scheme')->default('https');$t->unsignedSmallInteger('daemon_port')->default(8080);$t->unsignedSmallInteger('sftp_port')->default(2022);$t->string('token',128);$t->unsignedInteger('memory_mb');$t->unsignedInteger('disk_mb');$t->string('location')->nullable();$t->timestamps();});
  Schema::create('servers', function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('uuid')->unique();$t->string('name');$t->unsignedBigInteger('owner_id');$t->foreignId('node_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('template_id')->nullable();$t->string('docker_image');$t->text('startup');$t->unsignedInteger('memory_mb');$t->unsignedInteger('disk_mb');$t->unsignedInteger('cpu_limit')->default(100);$t->string('status')->default('offline');$t->json('environment')->nullable();$t->timestamps();});
  Schema::create('allocations', function(Blueprint $t){$t->id();$t->foreignId('node_id')->constrained()->cascadeOnDelete();$t->uuid('server_id')->nullable();$t->string('ip');$t->unsignedSmallInteger('port');$t->string('alias')->nullable();$t->timestamps();$t->unique(['node_id','ip','port']);});
  Schema::create('backups', function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('server_id');$t->string('name');$t->string('disk')->default('local');$t->unsignedBigInteger('bytes')->default(0);$t->string('checksum')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamps();});
  Schema::create('schedules', function(Blueprint $t){$t->id();$t->uuid('server_id');$t->string('name');$t->string('cron_minute');$t->string('cron_hour');$t->string('cron_day_of_month');$t->string('cron_month');$t->string('cron_day_of_week');$t->boolean('enabled')->default(true);$t->timestamps();});
  Schema::create('schedule_tasks', function(Blueprint $t){$t->id();$t->foreignId('schedule_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('sequence');$t->string('action');$t->text('payload')->nullable();$t->unsignedInteger('time_offset')->default(0);$t->timestamps();});
  Schema::create('subusers', function(Blueprint $t){$t->id();$t->uuid('server_id');$t->unsignedBigInteger('user_id');$t->json('permissions');$t->timestamps();$t->unique(['server_id','user_id']);});
  Schema::create('templates', function(Blueprint $t){$t->id();$t->string('name');$t->string('author')->nullable();$t->string('docker_image');$t->text('startup');$t->json('variables')->nullable();$t->json('config')->nullable();$t->timestamps();});
 }
 public function down(): void { foreach(['templates','subusers','schedule_tasks','schedules','backups','allocations','servers','nodes'] as $x) Schema::dropIfExists($x); }
};
