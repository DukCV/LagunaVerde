<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega el estado 'scheduled' al ENUM status de la tabla news
//
//  PROPÓSITO:
//   Soportar la programación de publicaciones futuras desde el formulario de
//   administración (NewsForm). Cuando el admin pulsa "Publicar" con una fecha
//   de publicación futura, la noticia se guarda con status='scheduled' en
//   lugar de 'published', y un comando programado (news:publish-scheduled)
//   la transiciona automáticamente a 'published' cuando llega la fecha.
//
//  ESTRATEGIA:
//   Igual que en add_disabled_status_to_news_table.php: ALTER TABLE MODIFY
//   COLUMN para ampliar el ENUM sin reconstruir la tabla ni perder datos.
//
//  SEGURIDAD:
//   - No modifica datos existentes; solo expande el conjunto de valores permitidos.
//   - down() solo revierte si ninguna fila usa 'scheduled', preservando datos.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande el ENUM status para incluir 'scheduled'.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('news', 'status')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `news`
             MODIFY COLUMN `status`
             ENUM('draft', 'published', 'archived', 'disabled', 'scheduled')
             NOT NULL
             DEFAULT 'draft'"
        );
    }

    /**
     * Revierte el ENUM a los cuatro valores previos.
     *
     * PROTECCIÓN DE DATOS: si existen filas con status = 'scheduled', el
     * rollback no se ejecuta para evitar truncar datos reales.
     *
     * @throws \RuntimeException si hay filas con status 'scheduled' que se perderían
     */
    public function down(): void
    {
        if (! Schema::hasColumn('news', 'status')) {
            return;
        }

        $filasProgramadas = DB::table('news')
            ->where('status', 'scheduled')
            ->count();

        if ($filasProgramadas > 0) {
            throw new \RuntimeException(
                "No se puede revertir la migración: existen {$filasProgramadas} " .
                "fila(s) con status = 'scheduled'. Actualiza o elimina esas filas " .
                "antes de ejecutar php artisan migrate:rollback."
            );
        }

        DB::statement(
            "ALTER TABLE `news`
             MODIFY COLUMN `status`
             ENUM('draft', 'published', 'archived', 'disabled')
             NOT NULL
             DEFAULT 'draft'"
        );
    }
};
