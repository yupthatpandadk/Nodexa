<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->string('color', 16)->default('#42e9a6');
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('nodexa_role_user', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
            $table->primary(['role_id', 'user_id']);
            $table->foreign('role_id')->references('id')->on('nodexa_roles')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $now = now();
        $roles = [
            [
                'name' => 'Supporter',
                'description' => 'Kan se dashboard, brugere og servere uden at ændre systemet.',
                'color' => '#38bdf8',
                'permissions' => [
                    'admin.dashboard.view',
                    'admin.users.view',
                    'admin.servers.view',
                ],
            ],
            [
                'name' => 'Moderator',
                'description' => 'Kan administrere brugere og servere, men ikke systemopsætning.',
                'color' => '#a78bfa',
                'permissions' => [
                    'admin.dashboard.view',
                    'admin.users.view',
                    'admin.users.manage',
                    'admin.servers.view',
                    'admin.servers.manage',
                    'admin.nodes.view',
                ],
            ],
            [
                'name' => 'Manager',
                'description' => 'Bred administrativ adgang uden fuld root-adgang.',
                'color' => '#42e9a6',
                'permissions' => [
                    'admin.dashboard.view',
                    'admin.users.*',
                    'admin.servers.*',
                    'admin.nodes.*',
                    'admin.locations.*',
                    'admin.databases.*',
                    'admin.mounts.*',
                    'admin.nests.*',
                    'admin.addons.*',
                    'admin.updates.view',
                    'admin.settings.view',
                    'admin.api.view',
                ],
            ],
        ];

        foreach ($roles as $role) {
            DB::table('nodexa_roles')->insert([
                'name' => $role['name'],
                'slug' => Str::slug($role['name']),
                'description' => $role['description'],
                'color' => $role['color'],
                'permissions' => json_encode($role['permissions']),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_role_user');
        Schema::dropIfExists('nodexa_roles');
    }
};
