<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodexa_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('subject', 180);
            $table->enum('category', ['support', 'billing', 'server', 'other'])->default('support');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'answered', 'customer_reply', 'closed'])->default('open');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'last_reply_at']);
        });

        Schema::create('nodexa_ticket_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedInteger('user_id');
            $table->text('message');
            $table->boolean('is_staff')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('nodexa_tickets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodexa_ticket_messages');
        Schema::dropIfExists('nodexa_tickets');
    }
};
