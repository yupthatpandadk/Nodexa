<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_proxmox_templates')) {
            Schema::create('nodexa_proxmox_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('connection_id');
                $table->string('preset_key', 48);
                $table->string('node', 80);
                $table->unsignedInteger('source_vmid');
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->unique(['connection_id', 'preset_key'], 'proxmox_template_preset_unique');
                $table->index(['connection_id', 'source_vmid']);
            });
        }

        if (!Schema::hasTable('nodexa_proxmox_deployments')) {
            Schema::create('nodexa_proxmox_deployments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('connection_id');
                $table->unsignedBigInteger('template_id');
                $table->string('preset_key', 48);
                $table->string('node', 80);
                $table->unsignedInteger('source_vmid');
                $table->unsignedInteger('vmid');
                $table->string('name', 120);
                $table->string('status', 25)->default('reserved');
                $table->string('clone_upid', 512)->nullable();
                $table->string('start_upid', 512)->nullable();
                $table->boolean('auto_start')->default(true);
                $table->string('last_error', 255)->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->unique(['connection_id', 'vmid'], 'proxmox_deployment_vmid_unique');
                $table->index(['status', 'updated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_proxmox_deployments');
        Schema::dropIfExists('nodexa_proxmox_templates');
    }
};
