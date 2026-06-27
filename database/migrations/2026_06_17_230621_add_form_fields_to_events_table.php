<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: agrega columnas del formulario de eventos a la tabla 'events'
//
//  JUSTIFICACIÓN:
//   AdminEventsForm (Livewire) necesita modalidad, enlace virtual, coordenadas
//   del mapa, fecha de publicación informativa y ventana de inscripción. Estas
//   columnas no existían en la migración original de 'events'.
//
//  SEGURIDAD EN PRODUCCIÓN (Hostinger):
//   - Todas las columnas son nullable o tienen un default — ninguna requiere
//     recalcular valores en filas existentes. En MySQL 8.0.12+ esto es DDL
//     prácticamente instantáneo (no reescribe la tabla completa).
//   - No se modifica ni se elimina ninguna columna existente.
//   - 'location' (dirección) y 'capacity_total' (0 = ilimitado) ya existían y
//     se reutilizan tal cual — no requieren cambios.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Fecha de publicación informativa (sin lógica de "programado")
            $table->dateTime('published_at')->nullable()->after('status');

            // Modalidad y ubicación virtual
            $table->enum('modality', ['presencial', 'virtual', 'hibrido'])
                  ->nullable()
                  ->after('published_at');
            $table->string('virtual_link', 500)->nullable()->after('modality');

            // Coordenadas del pin del mapa interactivo
            $table->decimal('map_lat', 10, 7)->nullable()->after('virtual_link');
            $table->decimal('map_lng', 10, 7)->nullable()->after('map_lat');

            // Ventana de inscripción (independiente de start_at/end_at del evento)
            $table->boolean('registration_enabled')->default(false)->after('map_lng');
            $table->dateTime('registration_start_at')->nullable()->after('registration_enabled');
            $table->dateTime('registration_end_at')->nullable()->after('registration_start_at');
            $table->boolean('registration_no_end_date')->default(false)->after('registration_end_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'published_at',
                'modality',
                'virtual_link',
                'map_lat',
                'map_lng',
                'registration_enabled',
                'registration_start_at',
                'registration_end_at',
                'registration_no_end_date',
            ]);
        });
    }
};
