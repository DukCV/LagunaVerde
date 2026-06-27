<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: tabla page_visits — registro de visitas al sitio público
//
//  Propósito: almacenar métricas de tráfico reales para el dashboard admin.
//  Cada visita GET (no-AJAX) al sitio público genera un registro aquí.
//
//  ÍNDICES:
//   - visited_at → consultas de rango temporal (gráfico de tráfico por día/semana/año)
//   - session_id → identificación de visitantes únicos por sesión
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();

            // Identificador de sesión Laravel (no expone datos personales)
            $table->string('session_id', 255)->nullable();

            // IP del visitante (IPv4 / IPv6 caben en 45 chars)
            $table->string('ip_address', 45)->nullable();

            // Ruta relativa visitada, truncada a 500 chars por seguridad
            $table->string('url', 500)->nullable();

            // Marca de tiempo de la visita (columna principal de filtrado)
            $table->timestamp('visited_at')->useCurrent();

            $table->timestamps();

            // Índice para agrupar visitas por rango de tiempo en el gráfico
            $table->index('visited_at');

            // Índice para contar visitantes únicos por sesión
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
