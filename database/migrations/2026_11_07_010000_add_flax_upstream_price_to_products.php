<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nodexa_flax_products') && !Schema::hasColumn('nodexa_flax_products', 'upstream_monthly_price')) {
            Schema::table('nodexa_flax_products', function (Blueprint $table) {
                $table->decimal('upstream_monthly_price', 12, 2)->nullable()->after('upstream_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nodexa_flax_products') && Schema::hasColumn('nodexa_flax_products', 'upstream_monthly_price')) {
            Schema::table('nodexa_flax_products', function (Blueprint $table) {
                $table->dropColumn('upstream_monthly_price');
            });
        }
    }
};
