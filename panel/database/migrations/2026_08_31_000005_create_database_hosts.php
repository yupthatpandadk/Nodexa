<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('database_hosts', function (Blueprint $t) {
            $t->id();
            $t->string('name',120);
            $t->string('host',255);
            $t->unsignedSmallInteger('port')->default(3306);
            $t->string('username',64);
            $t->text('password');
            $t->string('remote_host',255)->default('%');
            $t->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();
            $t->unsignedInteger('max_databases')->nullable();
            $t->boolean('ssl')->default(false);
            $t->boolean('enabled')->default(true);
            $t->timestamp('last_checked_at')->nullable();
            $t->string('last_status',32)->default('unknown');
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->unique(['host','port','username']);
        });

        Schema::table('server_databases', function (Blueprint $t) {
            $t->foreignId('database_host_id')->nullable()->after('server_id')->constrained('database_hosts')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('server_databases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('database_host_id');
        });
        Schema::dropIfExists('database_hosts');
    }
};
