<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: comments  (polimórfica — una sola tabla para todos los modelos)
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // Relación polimórfica
            $table->string('commentable_type', 160);
            $table->unsignedBigInteger('commentable_id');

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->text('body');

            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id'], 'idx_comments_commentable');
            $table->index('user_id', 'idx_comments_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
