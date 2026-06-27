<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega el estado 'disabled' al ENUM status de la tabla news
//
//  PROBLEMA QUE RESUELVE:
//   La columna status fue creada con ENUM('draft','published','archived').
//   Al intentar actualizar una fila con status = 'disabled', MySQL lanzaba:
//     SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status'
//   Con strict mode activado (config/database.php → 'strict' => true), ese
//   warning se convierte en excepción PDO, bloqueando completamente el toggle.
//
//  ESTRATEGIA:
//   Se usa DB::statement() con ALTER TABLE MODIFY COLUMN en lugar de
//   Schema::table()->enum() porque Laravel no provee un método nativo para
//   modificar ENUM sin recrear la columna completa, lo que podría perder datos
//   en algunos motores. ALTER TABLE MODIFY es atómico y seguro en MySQL/MariaDB.
//
//  SEGURIDAD:
//   - No modifica datos existentes; solo expande el conjunto de valores permitidos.
//   - down() solo hace rollback si ninguna fila usa 'disabled', preservando datos.
//   - No aplica to_lower ni transformación a datos existentes.
//
//  COMPATIBILIDAD:
//   - MySQL 5.7+ y MariaDB 10.3+ soportan ALTER TABLE MODIFY COLUMN en ENUM
//     sin reconstruir la tabla completa cuando solo se agregan valores al final.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande el ENUM status para incluir 'disabled'.
     *
     * ALTER TABLE MODIFY COLUMN es preferible a cambiar con Blueprint porque:
     *  - Es una operación DDL atómica en InnoDB (MySQL 5.7+ / MariaDB 10.3+).
     *  - Preserva todos los datos existentes sin conversión.
     *  - Mantiene el DEFAULT 'draft' y el NOT NULL implícito del ENUM.
     *  - No deshabilita restricciones de clave foránea temporalmente.
     */
    public function up(): void
    {
        // Verificar que la tabla y columna existen antes de modificar
        if (! Schema::hasColumn('news', 'status')) {
            return;
        }

        // Ampliar el ENUM agregando 'disabled' al final de la lista de valores.
        // Agregar al final minimiza la reconstrucción interna del índice en MySQL.
        DB::statement(
            "ALTER TABLE `news`
             MODIFY COLUMN `status`
             ENUM('draft', 'published', 'archived', 'disabled')
             NOT NULL
             DEFAULT 'draft'"
        );
    }

    /**
     * Revierte el ENUM a los tres valores originales.
     *
     * PROTECCIÓN DE DATOS:
     *  Si existen filas con status = 'disabled', el rollback NO se ejecuta para
     *  evitar truncar datos reales. Lanza una excepción descriptiva en su lugar.
     *
     * @throws \RuntimeException si hay filas con status 'disabled' que se perderían
     */
    public function down(): void
    {
        if (! Schema::hasColumn('news', 'status')) {
            return;
        }

        // Contar filas que usan el estado 'disabled' antes de revertir
        $filasDeshabilitadas = DB::table('news')
            ->where('status', 'disabled')
            ->count();

        // Abortar rollback si hay datos que se perderían al reducir el ENUM
        if ($filasDeshabilitadas > 0) {
            throw new \RuntimeException(
                "No se puede revertir la migración: existen {$filasDeshabilitadas} " .
                "fila(s) con status = 'disabled'. Actualiza o elimina esas filas " .
                "antes de ejecutar php artisan migrate:rollback."
            );
        }

        // Revertir al ENUM original con solo tres valores
        DB::statement(
            "ALTER TABLE `news`
             MODIFY COLUMN `status`
             ENUM('draft', 'published', 'archived')
             NOT NULL
             DEFAULT 'draft'"
        );
    }
};
