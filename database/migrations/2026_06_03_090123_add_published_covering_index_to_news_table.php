<?php

// ═══════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: índice compuesto (status, published_at) en la tabla news
//
//  PROBLEMA QUE RESUELVE:
//   La tabla news tiene tres índices simples independientes:
//     INDEX(status), INDEX(published_at), INDEX(category_id)
//
//   El scope scopePublished() genera siempre:
//     WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= now()
//
//   Con índices simples, MySQL debe elegir entre ellos y puede requerir
//   una operación de Index Merge (fusión de dos árboles B+) para resolver
//   ambas condiciones simultáneamente. Esto tiene un coste O(n) en datasets
//   grandes y degrada progresivamente al crecer la tabla.
//
//  SOLUCIÓN:
//   Un índice compuesto (status, published_at) resuelve ambas condiciones
//   en un único recorrido del árbol B+:
//     1. MySQL usa la parte 'status' para filtrar directamente el subset
//        de filas con status = 'published'.
//     2. Dentro de ese subset, 'published_at' ya está ordenado → el rango
//        "<= now()" se resuelve con una búsqueda binaria O(log n).
//
//   Resultado: query de scopePublished() pasa de O(n) a O(log n)
//   independientemente del tamaño de la tabla.
//
//  POR QUÉ SE ELIMINA EL ÍNDICE SIMPLE EN status:
//   El compuesto (status, published_at) hace redundante al simple INDEX(status)
//   para las consultas del scope público. Mantener ambos desperdicia espacio
//   en disco y añade sobrecarga en cada INSERT/UPDATE sin beneficio adicional.
//   INDEX(published_at) se conserva porque se usa en ORDER BY published_at.
//
//  IMPACTO EN ESCRITURAS:
//   MySQL actualiza el índice compuesto en cada INSERT, UPDATE o DELETE.
//   El coste es O(log n) por operación — idéntico al del índice simple que
//   reemplaza — por lo que el impacto en escrituras es neutral.
//
//  COMPATIBILIDAD:
//   - MySQL 5.7+ e InnoDB: totalmente soportado.
//   - MariaDB 10.3+: totalmente soportado.
//   - SQLite (tests): Schema::table()->index() es compatible.
// ═══════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade el índice compuesto (status, published_at) y elimina el simple INDEX(status).
     *
     * ORDEN DE OPERACIONES:
     *  1. Crear el compuesto primero → evita el instante sin ningún índice en status.
     *  2. Eliminar el simple después → MySQL puede hacer el DROP sin lock de tabla completa.
     */
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Paso 1: Crear el índice compuesto de cobertura.
            // La columna 'status' va primero porque su selectividad es baja
            // (pocos valores posibles), lo que permite filtrar el mayor
            // subconjunto posible antes de evaluar 'published_at'.
            $table->index(
                ['status', 'published_at'],
                'news_status_published_at_index' // nombre explícito para down() seguro
            );

            // Paso 2: Eliminar el índice simple en status que ahora es redundante.
            // El compuesto cubre todas las consultas que usaban el simple.
            $table->dropIndex(['status']);
        });
    }

    /**
     * Revierte los cambios: restaura el índice simple y elimina el compuesto.
     *
     * El orden inverso garantiza que siempre exista al menos un índice en status,
     * evitando degradación de rendimiento durante el rollback.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Paso 1: Restaurar el índice simple en status antes de eliminar el compuesto.
            $table->index('status');

            // Paso 2: Eliminar el índice compuesto usando su nombre explícito.
            $table->dropIndex('news_status_published_at_index');
        });
    }
};
