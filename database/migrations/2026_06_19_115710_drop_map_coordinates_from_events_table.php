<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: elimina las coordenadas del mapa interactivo de 'events'
//
//  JUSTIFICACIÓN:
//   El formulario de eventos eliminó el mapa interactivo (Leaflet +
//   Nominatim) en favor de un campo de texto libre para 'location'. La vista
//   pública ya construye su iframe de Google Maps a partir de ese texto
//   (urlencode($event->location)) — 'map_lat'/'map_lng' quedaron sin uso.
//
//  SEGURIDAD EN PRODUCCIÓN (Hostinger):
//   - DROP COLUMN en MySQL 8.0+ reescribe la tabla, pero ambas columnas son
//     nullable y no tienen índices ni claves foráneas — operación segura
//     incluso con la tabla 'events' en uso.
//   - No se modifica ninguna otra columna existente.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['map_lat', 'map_lng']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('map_lat', 10, 7)->nullable()->after('virtual_link');
            $table->decimal('map_lng', 10, 7)->nullable()->after('map_lat');
        });
    }
};
