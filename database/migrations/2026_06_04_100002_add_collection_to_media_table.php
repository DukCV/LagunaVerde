<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega 'collection' a la tabla media
//
//  JUSTIFICACIÓN:
//   Sin esta columna, la clasificación de medios (portada / slider / documento)
//   dependía únicamente del tipo MIME, lo que no permite distinguir entre una
//   imagen de portada y una imagen de slider (ambas tienen mime 'image/jpeg').
//
//  VALORES VÁLIDOS:
//   'cover'    → imagen de portada de la entidad (news, event, etc.)
//   'slider'   → imagen o vídeo del carrusel multimedia
//   'document' → archivo descargable (PDF, DOCX, XLSX…)
//   NULL       → registros anteriores a esta migración (compatibilidad)
//
//  COMPATIBILIDAD:
//   - La columna es nullable → registros previos no necesitan actualización.
//   - AdminNewsItemDto usa fallback a mime-type cuando collection es null.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Columna nullable para no romper registros existentes
            $table->string('collection', 20)
                  ->nullable()
                  ->default(null)
                  ->after('mediable_id');

            // Índice compuesto optimiza el patrón más común:
            // WHERE mediable_type = ? AND mediable_id = ? AND collection = ?
            $table->index(
                ['mediable_type', 'mediable_id', 'collection'],
                'idx_media_mediable_collection'
            );
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('idx_media_mediable_collection');
            $table->dropColumn('collection');
        });
    }
};
