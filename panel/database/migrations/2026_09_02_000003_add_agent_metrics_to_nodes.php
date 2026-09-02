<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('agent_version', 64)->nullable()->after('health_message');
            $table->unsignedSmallInteger('agent_api_version')->nullable()->after('agent_version');
            $table->string('agent_hostname', 255)->nullable()->after('agent_api_version');
            $table->timestamp('agent_started_at')->nullable()->after('agent_hostname');
            $table->unsignedBigInteger('host_memory_total_bytes')->nullable()->after('agent_started_at');
            $table->unsignedBigInteger('host_memory_available_bytes')->nullable()->after('host_memory_total_bytes');
            $table->unsignedBigInteger('host_disk_total_bytes')->nullable()->after('host_memory_available_bytes');
            $table->unsignedBigInteger('host_disk_free_bytes')->nullable()->after('host_disk_total_bytes');
            $table->double('host_load_1')->nullable()->after('host_disk_free_bytes');
            $table->double('host_load_5')->nullable()->after('host_load_1');
            $table->double('host_load_15')->nullable()->after('host_load_5');
            $table->unsignedSmallInteger('host_cpu_count')->nullable()->after('host_load_15');
            $table->unsignedBigInteger('host_uptime_seconds')->nullable()->after('host_cpu_count');
            $table->timestamp('metrics_updated_at')->nullable()->after('host_uptime_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'agent_version',
                'agent_api_version',
                'agent_hostname',
                'agent_started_at',
                'host_memory_total_bytes',
                'host_memory_available_bytes',
                'host_disk_total_bytes',
                'host_disk_free_bytes',
                'host_load_1',
                'host_load_5',
                'host_load_15',
                'host_cpu_count',
                'host_uptime_seconds',
                'metrics_updated_at',
            ]);
        });
    }
};
