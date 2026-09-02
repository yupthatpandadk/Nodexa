<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('health_status', 16)->default('unknown')->after('location');
            $table->unsignedInteger('health_latency_ms')->nullable()->after('health_status');
            $table->timestamp('health_last_checked_at')->nullable()->after('health_latency_ms');
            $table->timestamp('health_last_seen_at')->nullable()->after('health_last_checked_at');
            $table->text('health_message')->nullable()->after('health_last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'health_status',
                'health_latency_ms',
                'health_last_checked_at',
                'health_last_seen_at',
                'health_message',
            ]);
        });
    }
};
