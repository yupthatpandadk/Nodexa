<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices','issued_at')) $table->timestamp('issued_at')->nullable()->after('status');
                if (!Schema::hasColumn('invoices','notes')) $table->text('notes')->nullable();
                if (!Schema::hasColumn('invoices','payment_method')) $table->string('payment_method',40)->nullable();
                if (!Schema::hasColumn('invoices','tax_rate')) $table->decimal('tax_rate',8,3)->default(0);
                if (!Schema::hasColumn('invoices','credit')) $table->decimal('credit',12,2)->default(0);
                if (!Schema::hasColumn('invoices','balance')) $table->decimal('balance',12,2)->default(0);
                if (!Schema::hasColumn('invoices','last_reminder_at')) $table->timestamp('last_reminder_at')->nullable();
                if (!Schema::hasColumn('invoices','reminder_count')) $table->unsignedInteger('reminder_count')->default(0);
                if (!Schema::hasColumn('invoices','cancelled_at')) $table->timestamp('cancelled_at')->nullable();
            });
        }
        if (!Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->bigIncrements('id'); $table->unsignedBigInteger('invoice_id')->index();
                $table->decimal('amount',12,2); $table->string('currency',3)->default('DKK');
                $table->string('method',40)->nullable(); $table->string('transaction_id',190)->nullable()->index();
                $table->text('notes')->nullable(); $table->timestamp('paid_at')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('invoice_recurring_profiles')) {
            Schema::create('invoice_recurring_profiles', function (Blueprint $table) {
                $table->bigIncrements('id'); $table->unsignedBigInteger('user_id')->index(); $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('description',255); $table->decimal('amount',12,2); $table->string('currency',3)->default('DKK');
                $table->enum('cycle',['monthly','quarterly','semiannually','annually'])->default('monthly');
                $table->date('next_invoice_date'); $table->unsignedInteger('due_days')->default(7); $table->boolean('active')->default(true); $table->timestamps();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('invoice_recurring_profiles'); Schema::dropIfExists('invoice_payments'); }
};
