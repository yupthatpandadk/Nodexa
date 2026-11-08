<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_flax_services')
            && !Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted')
        ) {
            Schema::table('nodexa_flax_services', function (Blueprint $table) {
                $table->text('credential_password_encrypted')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_flax_services')
            && Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted')
        ) {
            Schema::table('nodexa_flax_services', function (Blueprint $table) {
                $table->dropColumn('credential_password_encrypted');
            });
        }
    }
};
