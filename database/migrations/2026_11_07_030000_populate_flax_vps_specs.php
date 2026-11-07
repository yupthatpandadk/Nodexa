<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_flax_products')) {
            return;
        }

        $specs = [
            1  => ['vcpu' => 1,  'memory_mb' => 4096,  'disk_gb' => 40,  'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            2  => ['vcpu' => 2,  'memory_mb' => 8192,  'disk_gb' => 80,  'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            3  => ['vcpu' => 3,  'memory_mb' => 12288, 'disk_gb' => 120, 'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            4  => ['vcpu' => 4,  'memory_mb' => 16384, 'disk_gb' => 160, 'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            5  => ['vcpu' => 6,  'memory_mb' => 24576, 'disk_gb' => 200, 'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            6  => ['vcpu' => 8,  'memory_mb' => 32768, 'disk_gb' => 250, 'bandwidth' => '1 Gbit/s · 20 TB traffic'],
            7  => ['vcpu' => 2,  'memory_mb' => 4096,  'disk_gb' => 80,  'bandwidth' => '1 Gbit/s · Unlimited traffic'],
            8  => ['vcpu' => 4,  'memory_mb' => 8192,  'disk_gb' => 160, 'bandwidth' => '1 Gbit/s · Unlimited traffic'],
            9  => ['vcpu' => 6,  'memory_mb' => 12288, 'disk_gb' => 240, 'bandwidth' => '1 Gbit/s · Unlimited traffic'],
            10 => ['vcpu' => 8,  'memory_mb' => 16384, 'disk_gb' => 320, 'bandwidth' => '1 Gbit/s · Unlimited traffic'],
            11 => ['vcpu' => 12, 'memory_mb' => 24576, 'disk_gb' => 400, 'bandwidth' => '1 Gbit/s · Unlimited traffic'],
            12 => ['vcpu' => 16, 'memory_mb' => 32768, 'disk_gb' => 500, 'bandwidth' => '1 Gbit/s · Unlimited traffic'],
        ];

        foreach ($specs as $productId => $spec) {
            DB::table('nodexa_flax_products')
                ->where('upstream_product_id', $productId)
                ->update(array_merge($spec, [
                    'ipv4_count' => 1,
                    'updated_at' => now(),
                ]));
        }
    }

    public function down(): void
    {
        // Non-destructive: product specs are intentionally retained.
    }
};
