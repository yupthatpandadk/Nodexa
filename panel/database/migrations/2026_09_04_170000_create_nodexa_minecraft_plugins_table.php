<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_minecraft_plugins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id');
            $table->string('project_id', 64);
            $table->string('slug', 128)->nullable();
            $table->string('name', 191);
            $table->string('version_id', 64);
            $table->string('version_number', 128)->nullable();
            $table->string('filename', 255);
            $table->string('loader', 32)->nullable();
            $table->string('game_version', 32)->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'project_id']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_minecraft_plugins');
    }
};
