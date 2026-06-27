<?php

// ═══════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: permite category_id NULL en la tabla news
//
//  PROPÓSITO:
//   Habilitar el guardado flexible de borradores (status='draft') sin exigir
//   una categoría — requisito de negocio: "Guardar borrador" debe omitir los
//   campos obligatorios de publicación (ver NewsForm::reglasArchivos()).
//   Sin esta migración, el guardado de un borrador sin categoría fallaría
//   por la restricción NOT NULL + clave foránea de la columna original.
//
//  COMPATIBILIDAD:
//   No se modifica la relación de clave foránea con 'categories': se
//   conserva restrictOnDelete() (no se puede borrar una categoría referenciada)
//   y cascadeOnUpdate(). Los valores NULL simplemente no participan en la
//   restricción de integridad referencial — comportamiento estándar de SQL.
//
//  PROCEDIMIENTO (sin doctrine/dbal):
//   1. Eliminar la clave foránea existente (requisito de MySQL para modificar
//      una columna referenciada).
//   2. Modificar la columna a NULL mediante SQL crudo (ALTER TABLE MODIFY).
//   3. Recrear la clave foránea con las mismas reglas de integridad.
// ═══════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        DB::statement('ALTER TABLE news MODIFY category_id BIGINT UNSIGNED NULL');

        Schema::table('news', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Revierte a NOT NULL.
     *
     * SEGURIDAD DE DATOS: si existieran borradores guardados sin categoría,
     * la reversión fallaría por la restricción NOT NULL — comportamiento
     * deseado, ya que evita perder silenciosamente la asociación de datos.
     * El operador debería asignar una categoría a esos registros antes de
     * revertir esta migración.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        DB::statement('ALTER TABLE news MODIFY category_id BIGINT UNSIGNED NOT NULL');

        Schema::table('news', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
