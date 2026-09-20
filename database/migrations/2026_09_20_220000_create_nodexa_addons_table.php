<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_addons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('game', 100)->index();
            $table->string('category', 100)->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('version', 100)->nullable();
            $table->string('compatible_versions', 255)->nullable();
            $table->text('download_url');
            $table->string('install_path', 255);
            $table->string('egg_ids', 255)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['game', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_addons');
    }
};
