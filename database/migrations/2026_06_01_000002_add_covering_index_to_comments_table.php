<?php

// Migración: índice cubriente en comments para la consulta principal de comentarios.
//
// La query es: WHERE commentable_type = ? AND commentable_id = ? ORDER BY created_at DESC
// El índice anterior (idx_comments_commentable) cubre el WHERE pero fuerza un filesort
// en el ORDER BY. Este nuevo índice incluye created_at como tercer campo, convirtiendo
// el plan de ejecución en un index-range scan sin sort adicional.
//
// IMPORTANTE: no elimina el índice anterior porque MySQL puede preferir el
// de dos columnas para búsquedas sin ORDER BY (p.ej. EXISTS en userCommentedRecently).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->index(
                ['commentable_type', 'commentable_id', 'created_at'],
                'idx_comments_commentable_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('idx_comments_commentable_date');
        });
    }
};
