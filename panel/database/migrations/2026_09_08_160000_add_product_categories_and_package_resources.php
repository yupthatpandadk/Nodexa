<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_categories')) {
            Schema::create('billing_categories', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('slug')->unique(); $t->text('description')->nullable(); $t->integer('sort_order')->default(0); $t->boolean('active')->default(true); $t->timestamps();
            });
        }
        Schema::table('billing_products', function (Blueprint $t) {
            if (!Schema::hasColumn('billing_products', 'category_id')) $t->unsignedBigInteger('category_id')->nullable()->index();
            if (!Schema::hasColumn('billing_products', 'memory_mb')) $t->unsignedInteger('memory_mb')->nullable();
            if (!Schema::hasColumn('billing_products', 'disk_mb')) $t->unsignedInteger('disk_mb')->nullable();
            if (!Schema::hasColumn('billing_products', 'cpu_percent')) $t->unsignedInteger('cpu_percent')->nullable();
            if (!Schema::hasColumn('billing_products', 'databases_limit')) $t->unsignedInteger('databases_limit')->default(0);
            if (!Schema::hasColumn('billing_products', 'backups_limit')) $t->unsignedInteger('backups_limit')->default(0);
            if (!Schema::hasColumn('billing_products', 'allocations_limit')) $t->unsignedInteger('allocations_limit')->default(1);
            if (!Schema::hasColumn('billing_products', 'sort_order')) $t->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('billing_products', function (Blueprint $t) {
            foreach (['category_id','memory_mb','disk_mb','cpu_percent','databases_limit','backups_limit','allocations_limit','sort_order'] as $column) if (Schema::hasColumn('billing_products', $column)) $t->dropColumn($column);
        });
        Schema::dropIfExists('billing_categories');
    }
};
