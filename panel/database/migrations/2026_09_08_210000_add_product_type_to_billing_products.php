<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_products')) return;

        if (!Schema::hasColumn('billing_products', 'product_type')) {
            Schema::table('billing_products', function (Blueprint $table) {
                $table->string('product_type', 20)->default('game')->index();
            });
        }

        // Existing imported Flax VPS packages are identified once during migration.
        // From this point forward their placement is controlled exclusively by product_type.
        DB::table('billing_products')
            ->where(function ($query) {
                $query->where('name', 'like', 'VPS-%')
                    ->orWhere('description', 'like', '%Flax Hosting%');
            })
            ->update(['product_type' => 'vps']);
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_products') && Schema::hasColumn('billing_products', 'product_type')) {
            Schema::table('billing_products', function (Blueprint $table) {
                $table->dropColumn('product_type');
            });
        }
    }
};
