<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('nodes', 'setup_options')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->json('setup_options')->nullable()->after('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nodes', 'setup_options')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->dropColumn('setup_options');
            });
        }
    }
};
