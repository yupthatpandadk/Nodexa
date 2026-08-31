<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('mode', 20)->default('advanced')->after('name');
            $table->string('timezone', 64)->default('Europe/Copenhagen')->after('cron_day_of_week');
            $table->boolean('only_when_online')->default(false)->after('enabled');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
        });
        Schema::table('schedule_tasks', function (Blueprint $table) {
            $table->boolean('continue_on_failure')->default(false)->after('time_offset');
        });
    }

    public function down(): void {
        Schema::table('schedule_tasks', fn (Blueprint $table) => $table->dropColumn('continue_on_failure'));
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['mode','timezone','only_when_online','last_run_at','next_run_at']);
        });
    }
};
