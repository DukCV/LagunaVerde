<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: news
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 220);
            $table->text('summary')->nullable();
            $table->string('author_name', 150)->nullable();

            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->dateTime('published_at')->nullable();
            $table->longText('content');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->timestamps();

            $table->index('category_id');
            $table->index('published_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
