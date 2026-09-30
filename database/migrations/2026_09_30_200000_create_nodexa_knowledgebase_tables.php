<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledgebase_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 40)->default('fa-book');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledgebase_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('knowledgebase_categories')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('content');
            $table->boolean('published')->default(true);
            $table->boolean('featured')->default(false);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category_id', 'published', 'sort_order']);
            $table->index(['published', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledgebase_articles');
        Schema::dropIfExists('knowledgebase_categories');
    }
};
