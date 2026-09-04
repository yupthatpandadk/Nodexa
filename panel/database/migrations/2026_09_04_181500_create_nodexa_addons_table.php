<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_addons')) {
            return;
        }

        Schema::create('nodexa_addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('version', 40);
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_addons');
    }
};
