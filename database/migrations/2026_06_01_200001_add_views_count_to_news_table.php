<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega views_count a la tabla news
//
//  Propósito: permitir ordenar noticias por popularidad en el widget de noticias
//  del dashboard. El contador se incrementa con DB::increment() al visitar el
//  detalle de cada noticia — no usa mass assignment.
//
//  ÍNDICE: views_count → ORDER BY views_count DESC/ASC sin full table scan
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // Contador de vistas: se incrementa atómicamente con increment()
            $table->unsignedBigInteger('views_count')->default(0)->after('status');

            // Índice para filtros de popularidad en el widget de noticias
            $table->index('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropIndex(['views_count']);
            $table->dropColumn('views_count');
        });
    }
};
