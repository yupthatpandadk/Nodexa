<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nodexa_status_components')) {
            Schema::create('nodexa_status_components', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('description', 500)->nullable();
                $table->string('status', 40)->default('operational');
                $table->boolean('is_public')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_incidents')) {
            Schema::create('nodexa_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('title', 180);
                $table->string('status', 40)->default('investigating');
                $table->string('severity', 40)->default('minor');
                $table->text('message');
                $table->boolean('published')->default(true);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_incident_updates')) {
            Schema::create('nodexa_incident_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id');
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('status', 40);
                $table->text('message');
                $table->timestamps();
                $table->foreign('incident_id')->references('id')->on('nodexa_incidents')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('nodexa_notifications')) {
            Schema::create('nodexa_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->string('type', 60)->default('info');
                $table->string('title', 180);
                $table->text('message');
                $table->string('url', 500)->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'read_at']);
            });
        }

        if (!Schema::hasTable('nodexa_audit_logs')) {
            Schema::create('nodexa_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('area', 80);
                $table->string('action', 120);
                $table->string('target_type', 120)->nullable();
                $table->string('target_id', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['area', 'action']);
            });
        }

        if (!Schema::hasTable('nodexa_backup_policies')) {
            Schema::create('nodexa_backup_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('server_id')->unique();
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('frequency_hours')->default(24);
                $table->unsignedInteger('retention_count')->default(3);
                $table->string('name_prefix', 120)->default('Automatic backup');
                $table->timestamp('next_run_at')->nullable()->index();
                $table->timestamp('last_run_at')->nullable();
                $table->string('last_status', 40)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_subscriptions')) {
            Schema::create('nodexa_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('server_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('status', 40)->default('active');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('DKK');
                $table->string('interval', 20)->default('monthly');
                $table->timestamp('next_invoice_at')->nullable()->index();
                $table->timestamp('cancel_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_invoices')) {
            Schema::create('nodexa_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                $table->string('number', 40)->unique();
                $table->string('status', 40)->default('unpaid');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('tax', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('currency', 8)->default('DKK');
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_method', 80)->nullable();
                $table->string('payment_reference', 191)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_invoice_items')) {
            Schema::create('nodexa_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->string('description', 255);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('total', 12, 2);
                $table->timestamps();
                $table->foreign('invoice_id')->references('id')->on('nodexa_invoices')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('nodexa_store_addons')) {
            Schema::create('nodexa_store_addons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('description', 500)->nullable();
                $table->decimal('price_monthly', 12, 2)->default(0);
                $table->integer('memory_delta')->default(0);
                $table->integer('disk_delta')->default(0);
                $table->integer('cpu_delta')->default(0);
                $table->integer('backup_delta')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_service_addons')) {
            Schema::create('nodexa_service_addons', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('server_id')->index();
                $table->unsignedBigInteger('addon_id')->index();
                $table->string('status', 40)->default('active');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->unique(['server_id', 'addon_id']);
            });
        }

        if (!Schema::hasTable('nodexa_affiliates')) {
            Schema::create('nodexa_affiliates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->unique();
                $table->string('code', 60)->unique();
                $table->decimal('commission_percent', 5, 2)->default(10);
                $table->decimal('balance', 12, 2)->default(0);
                $table->decimal('paid_total', 12, 2)->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_affiliate_events')) {
            Schema::create('nodexa_affiliate_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('affiliate_id')->index();
                $table->unsignedInteger('referred_user_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('type', 40);
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('commission', 12, 2)->default(0);
                $table->longText('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_organizations')) {
            Schema::create('nodexa_organizations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('owner_id')->index();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_organization_members')) {
            Schema::create('nodexa_organization_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedInteger('user_id')->index();
                $table->string('role', 40)->default('member');
                $table->timestamps();
                $table->unique(['organization_id', 'user_id']);
                $table->foreign('organization_id')->references('id')->on('nodexa_organizations')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('nodexa_webhooks')) {
            Schema::create('nodexa_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('url', 1000);
                $table->string('secret', 191);
                $table->text('events');
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_delivery_at')->nullable();
                $table->string('last_status', 40)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_webhook_deliveries')) {
            Schema::create('nodexa_webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('webhook_id')->index();
                $table->string('event', 100);
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('nodexa_api_tokens')) {
            Schema::create('nodexa_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->string('name', 120);
                $table->string('token_prefix', 24);
                $table->string('token_hash', 64)->unique();
                $table->text('scopes');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_health_checks')) {
            Schema::create('nodexa_health_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('node_id')->index();
                $table->string('status', 40);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->text('message')->nullable();
                $table->timestamp('checked_at')->index();
            });
        }

        if (!Schema::hasTable('nodexa_kb_feedback')) {
            Schema::create('nodexa_kb_feedback', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('article_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->boolean('helpful');
                $table->string('fingerprint', 64)->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('nodexa_kb_revisions')) {
            Schema::create('nodexa_kb_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('article_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('title', 180);
                $table->string('summary', 500)->nullable();
                $table->longText('content');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'nodexa_kb_revisions',
            'nodexa_kb_feedback',
            'nodexa_health_checks',
            'nodexa_api_tokens',
            'nodexa_webhook_deliveries',
            'nodexa_webhooks',
            'nodexa_organization_members',
            'nodexa_organizations',
            'nodexa_affiliate_events',
            'nodexa_affiliates',
            'nodexa_service_addons',
            'nodexa_store_addons',
            'nodexa_invoice_items',
            'nodexa_invoices',
            'nodexa_subscriptions',
            'nodexa_backup_policies',
            'nodexa_audit_logs',
            'nodexa_notifications',
            'nodexa_incident_updates',
            'nodexa_incidents',
            'nodexa_status_components',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
