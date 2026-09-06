<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_issues', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('severity', 20)->default('error');
            $table->string('source', 40)->default('panel');
            $table->string('type', 80)->nullable();
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->unsignedBigInteger('node_id')->nullable()->index();
            $table->string('server_id', 64)->nullable()->index();
            $table->json('context')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_issues');
    }
};
