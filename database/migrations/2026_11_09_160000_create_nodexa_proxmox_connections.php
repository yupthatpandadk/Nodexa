<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_proxmox_connections')) {
            return;
        }

        Schema::create('nodexa_proxmox_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('endpoint', 255);
            $table->string('token_id', 255);
            $table->longText('token_secret_encrypted');
            $table->boolean('verify_tls')->default(true);
            $table->boolean('enabled')->default(true);
            $table->string('status', 24)->default('untested');
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->longText('nodes_snapshot')->nullable();
            $table->longText('guests_snapshot')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_proxmox_connections');
    }
};
