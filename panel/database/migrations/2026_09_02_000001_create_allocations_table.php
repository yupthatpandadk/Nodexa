<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->uuid('server_id')->nullable()->index();
            $table->string('ip', 255);
            $table->unsignedInteger('port');
            $table->string('alias')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['node_id', 'ip', 'port']);
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
    }
};
