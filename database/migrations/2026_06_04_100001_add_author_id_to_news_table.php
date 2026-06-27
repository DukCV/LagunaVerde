<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega author_id a la tabla news
//
//  JUSTIFICACIÓN:
//   La tabla existía solo con 'author_name' (string desnormalizado).
//   'author_id' añade la FK real a users para:
//     1. Vincular noticias al usuario autenticado en creación.
//     2. Permitir relaciones Eloquent (belongsTo / hasMany) para eager loading.
//     3. Habilitar filtros y estadísticas por autor sin búsqueda por string.
//
//  COMPATIBILIDAD CON DATOS EXISTENTES:
//   - La columna es nullable → los registros anteriores quedan intactos.
//   - nullOnDelete: si el usuario se elimina, author_id queda null (no se borra la noticia).
//   - cascadeOnUpdate: si el ID del usuario cambia, la FK se actualiza automáticamente.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // FK nullable para compatibilidad con noticias creadas antes de esta migración
            $table->foreignId('author_id')
                  ->nullable()
                  ->after('author_name')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            // Índice para consultas de "noticias por autor" sin full-scan
            $table->index('author_id', 'idx_news_author_id');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Eliminar FK y constraint antes de eliminar la columna
            $table->dropForeign(['author_id']);
            $table->dropIndex('idx_news_author_id');
            $table->dropColumn('author_id');
        });
    }
};
