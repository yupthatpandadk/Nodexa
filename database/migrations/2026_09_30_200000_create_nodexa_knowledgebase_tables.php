<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is intentionally repair-safe. MySQL can leave the
        // first table behind when a later CREATE TABLE fails, so each table is
        // checked separately before it is created.
        if (!Schema::hasTable('knowledgebase_categories')) {
            Schema::create('knowledgebase_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('description', 500)->nullable();
                $table->string('icon', 40)->default('📚');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('published')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('knowledgebase_articles')) {
            Schema::create('knowledgebase_articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')
                    ->constrained('knowledgebase_categories')
                    ->cascadeOnDelete();

                // Pterodactyl's users.id is an UNSIGNED INT. Laravel foreignId()
                // would create an UNSIGNED BIGINT and MySQL rejects the foreign
                // key because the column types do not match.
                $table->unsignedInteger('author_id')->nullable();

                $table->string('title', 180);
                $table->string('slug', 200)->unique();
                $table->string('summary', 500)->nullable();
                $table->longText('content');
                $table->boolean('published')->default(true);
                $table->boolean('featured')->default(false);
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('author_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(['category_id', 'published', 'sort_order']);
                $table->index(['published', 'featured']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledgebase_articles');
        Schema::dropIfExists('knowledgebase_categories');
    }
};
