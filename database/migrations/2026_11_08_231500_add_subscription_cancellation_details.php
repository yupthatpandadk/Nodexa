<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_subscriptions')) {
            return;
        }

        foreach ([
            'cancellation_requested_at',
            'cancellation_completed_at',
        ] as $column) {
            if (!Schema::hasColumn('nodexa_subscriptions', $column)) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) use ($column) {
                    $table->timestamp($column)->nullable();
                });
            }
        }

        if (!Schema::hasColumn('nodexa_subscriptions', 'cancellation_reason')) {
            Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                $table->string('cancellation_reason', 500)->nullable();
            });
        }

        if (!Schema::hasColumn('nodexa_subscriptions', 'cancellation_last_error')) {
            Schema::table('nodexa_subscriptions', function (Blueprint $table) {
                $table->text('cancellation_last_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('nodexa_subscriptions')) {
            return;
        }

        foreach ([
            'cancellation_last_error',
            'cancellation_reason',
            'cancellation_completed_at',
            'cancellation_requested_at',
        ] as $column) {
            if (Schema::hasColumn('nodexa_subscriptions', $column)) {
                Schema::table('nodexa_subscriptions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
