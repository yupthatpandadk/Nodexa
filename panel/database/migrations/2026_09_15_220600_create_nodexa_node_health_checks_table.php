<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_node_health_checks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('node_id');
            $table->boolean('reachable');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['node_id', 'checked_at']);
            $table->index(['reachable', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_node_health_checks');
    }
};
