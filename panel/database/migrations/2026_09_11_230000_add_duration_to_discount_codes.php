<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('discount_codes') && !Schema::hasColumn('discount_codes', 'duration')) {
            Schema::table('discount_codes', function (Blueprint $table) {
                $table->string('duration', 20)->default('once')->after('value');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('discount_codes') && Schema::hasColumn('discount_codes', 'duration')) {
            Schema::table('discount_codes', function (Blueprint $table) {
                $table->dropColumn('duration');
            });
        }
    }
};
