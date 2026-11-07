<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_flax_products') || !Schema::hasColumn('nodexa_flax_products', 'upstream_monthly_price')) {
            return;
        }

        $costs = [
            1 => 51.75,
            2 => 81.75,
            3 => 111.75,
            4 => 149.25,
            5 => 194.25,
            6 => 239.25,
            7 => 74.25,
            8 => 96.75,
            9 => 141.75,
            10 => 164.25,
            11 => 254.25,
            12 => 351.75,
        ];

        foreach ($costs as $productId => $cost) {
            DB::table('nodexa_flax_products')
                ->where('upstream_product_id', $productId)
                ->update([
                    'upstream_monthly_price' => $cost,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Older list prices are not restored
        // because they were not the reseller's actual "Din pris" values.
    }
};
