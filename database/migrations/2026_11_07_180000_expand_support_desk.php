<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_support_departments', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('name', 100);
            $t->string('email')->nullable();
            $t->boolean('enabled')->default(true);
            $t->boolean('hidden')->default(false);
            $t->boolean('prevent_client_close')->default(false);
            $t->unsignedInteger('auto_close_hours')->default(0);
            $t->unsignedInteger('escalation_minutes')->default(0);
            $t->string('escalation_priority', 20)->default('high');
            $t->timestamps();
        });
        Schema::create('nodexa_support_department_user', function (Blueprint $t) {
            $t->unsignedBigInteger('department_id');
            $t->unsignedInteger('user_id');
            $t->primary(['department_id', 'user_id']);
            $t->foreign('department_id')->references('id')->on('nodexa_support_departments')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        foreach (['support' => 'General Support', 'technical' => 'Technical Support', 'billing' => 'Billing', 'sales' => 'Sales'] as $key => $name) {
            $id = DB::table('nodexa_support_departments')->insertGetId(['key' => $key, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            // Preserve existing role-based support access on upgrade. Root admins always have access.
            if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                $roles = DB::table('roles')->get()->filter(fn ($role) => count(array_intersect(json_decode($role->permissions, true) ?: [], ['*', 'tickets.view', 'tickets.manage'])) > 0)->pluck('id');
                foreach (DB::table('role_user')->whereIn('role_id', $roles)->distinct()->pluck('user_id') as $userId) {
                    DB::table('nodexa_support_department_user')->insertOrIgnore(['department_id' => $id, 'user_id' => $userId]);
                }
            }
        }
        Schema::create('nodexa_support_canned_replies', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->string('title', 160);
            $t->text('body');
            $t->timestamps();
            $t->foreign('department_id')->references('id')->on('nodexa_support_departments')->cascadeOnDelete();
        });
        Schema::table('nodexa_support_tickets', function (Blueprint $t) {
            $t->unsignedBigInteger('merged_into_id')->nullable();
            $t->string('reply_token', 64)->nullable()->unique();
            $t->timestamp('escalated_at')->nullable();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->text('feedback')->nullable();
            $t->timestamp('rated_at')->nullable();
            $t->foreign('merged_into_id')->references('id')->on('nodexa_support_tickets')->nullOnDelete();
            $t->index(['status', 'due_at']);
            $t->index(['department', 'status', 'last_staff_reply_at'], 'support_inactive_lookup');
        });
        Schema::table('nodexa_support_ticket_messages', function (Blueprint $t) {
            $t->unsignedTinyInteger('rating')->nullable();
            $t->timestamp('rated_at')->nullable();
        });
        Schema::create('nodexa_support_mail_imports', function (Blueprint $t) {
            $t->id();
            $t->string('message_key', 64)->unique();
            $t->string('sender')->nullable();
            $t->string('status', 30);
            $t->string('reason', 255)->nullable();
            $t->unsignedBigInteger('ticket_id')->nullable();
            $t->timestamps();
            $t->foreign('ticket_id')->references('id')->on('nodexa_support_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_support_mail_imports');
        Schema::table('nodexa_support_ticket_messages', fn (Blueprint $t) => $t->dropColumn(['rating', 'rated_at']));
        Schema::table('nodexa_support_tickets', function (Blueprint $t) {
            $t->dropForeign(['merged_into_id']);
            $t->dropUnique(['reply_token']);
            $t->dropIndex(['status', 'due_at']);
            $t->dropIndex('support_inactive_lookup');
            $t->dropColumn(['merged_into_id', 'reply_token', 'escalated_at', 'rating', 'feedback', 'rated_at']);
        });
        Schema::dropIfExists('nodexa_support_canned_replies');
        Schema::dropIfExists('nodexa_support_department_user');
        Schema::dropIfExists('nodexa_support_departments');
    }
};
