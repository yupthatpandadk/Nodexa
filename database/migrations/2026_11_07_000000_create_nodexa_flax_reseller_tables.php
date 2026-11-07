<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_flax_settings')) {
            Schema::create('nodexa_flax_settings', function (Blueprint $table) {
                $table->id();
                $table->string('api_url', 1000)->nullable();
                $table->longText('api_key_encrypted')->nullable();
                $table->boolean('enabled')->default(false);
                $table->timestamp('last_tested_at')->nullable();
                $table->string('last_test_status', 40)->nullable();
                $table->text('last_test_message')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_flax_products')) {
            Schema::create('nodexa_flax_products', function (Blueprint $table) {
                $table->id();
                $table->string('name', 140);
                $table->string('slug', 170)->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('upstream_product_id');
                $table->decimal('monthly_price', 12, 2);
                $table->string('currency', 8)->default('DKK');
                $table->unsignedInteger('memory_mb')->nullable();
                $table->unsignedSmallInteger('vcpu')->nullable();
                $table->unsignedInteger('disk_gb')->nullable();
                $table->unsignedSmallInteger('ipv4_count')->default(1);
                $table->string('bandwidth', 120)->nullable();
                $table->boolean('enabled')->default(true);
                $table->boolean('featured')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['enabled', 'sort_order']);
            });
        }

        if (!Schema::hasTable('nodexa_flax_os_templates')) {
            Schema::create('nodexa_flax_os_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('upstream_id')->unique();
                $table->string('label', 190);
                $table->string('name', 120)->nullable();
                $table->string('version', 80)->nullable();
                $table->string('variant', 120)->nullable();
                $table->longText('raw_payload')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_flax_services')) {
            Schema::create('nodexa_flax_services', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                $table->unsignedBigInteger('first_invoice_id')->nullable()->index();
                $table->string('upstream_server_id', 191)->nullable()->index();
                $table->string('hostname', 190);
                $table->unsignedInteger('os_template_id');
                $table->string('status', 50)->default('awaiting_payment')->index();
                $table->decimal('upstream_price', 12, 2)->nullable();
                $table->string('upstream_invoice_id', 191)->nullable();
                $table->string('upstream_invoice_number', 191)->nullable();
                $table->unsignedBigInteger('last_billed_invoice_id')->nullable()->index();
                $table->unsignedBigInteger('processing_invoice_id')->nullable()->index();
                $table->timestamp('processing_started_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->longText('server_info')->nullable();
                $table->timestamp('last_synced_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamp('provisioned_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('terminated_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_flax_services');
        Schema::dropIfExists('nodexa_flax_os_templates');
        Schema::dropIfExists('nodexa_flax_products');
        Schema::dropIfExists('nodexa_flax_settings');
    }
};
