<?php

// Índice compuesto (status, start_at): cubre el filtro + el ORDER BY de
// EventRepository::nextPublished() en una sola búsqueda — mismo criterio
// que el índice ya existente en 'news' (status, published_at).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Compuesto primero, para no dejar un instante sin índice en 'status'
            $table->index(['status', 'start_at'], 'events_status_start_at_index');

            // El simple en 'status' queda redundante con el compuesto
            $table->dropIndex(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('status');
            $table->dropIndex('events_status_start_at_index');
        });
    }
};
