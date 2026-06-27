<?php

// ═══════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega la columna first_published_at a la tabla news
//
//  PROPÓSITO:
//   Registrar de forma permanente el instante en que una noticia fue publicada
//   por primera vez. A diferencia de published_at (que puede reasignarse en
//   borradores), este campo actúa como marca inalterable de "alguna vez publicada".
//
//  LÓGICA DE NEGOCIO QUE HABILITA:
//   - Si first_published_at IS NOT NULL → la fecha de publicación se bloquea
//     en el formulario de edición (el admin no puede cambiarla).
//   - Si first_published_at IS NULL → la noticia nunca fue publicada; la fecha
//     permanece editable.
//
//  COLUMNA:
//   - Nullable: borradores nunca publicados tienen NULL.
//   - Se posiciona después de published_at para coherencia semántica.
//   - Índice simple: consultas de auditoría y filtros futuros.
//
//  INMUTABILIDAD EN BD:
//   La columna se escribe UNA VEZ (al publicar por primera vez) y nunca se
//   actualiza. Esta invariante se garantiza en NewsFormService::guardar().
// ═══════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega first_published_at como marca temporal inmutable de primera publicación.
     */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Nullable: los borradores nunca publicados mantienen NULL
            $table->timestamp('first_published_at')
                ->nullable()
                ->after('published_at');

            // Índice simple para consultas de auditoría y futuros filtros admin
            $table->index('first_published_at', 'news_first_published_at_idx');
        });
    }

    /**
     * Elimina la columna y su índice al revertir.
     *
     * SEGURIDAD DE DATOS:
     *  No valida filas existentes antes de eliminar porque la columna es puramente
     *  informativa; su ausencia no rompe la integridad referencial.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Eliminar índice primero para evitar error de FK residual
            $table->dropIndex('news_first_published_at_idx');
            $table->dropColumn('first_published_at');
        });
    }
};
