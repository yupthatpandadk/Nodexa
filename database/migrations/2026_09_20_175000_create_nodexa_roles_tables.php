<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->string('color', 20)->default('#60a5fa');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Fuld Nodexa-adgang uden Owner-status.', 'permissions' => json_encode(['*']), 'color' => '#8b5cf6', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Support', 'slug' => 'support', 'description' => 'Supportadgang til servere, brugere og mail.', 'permissions' => json_encode(['servers.view', 'servers.manage', 'users.view', 'mail.view', 'mail.manage']), 'color' => '#22c55e', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Moderator', 'slug' => 'moderator', 'description' => 'Læsning af servere og brugere.', 'permissions' => json_encode(['servers.view', 'users.view']), 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
