<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: clasifica retroactivamente los medios de eventos sin 'collection'
//
//  JUSTIFICACIÓN:
//   EventSeeder::attachMedia() creaba los registros de Media sin establecer
//   'collection' (solo fijaba 'order', usando 0 para la portada). Eso deja
//   'collection' en NULL para todo evento sembrado. AdminEventsFormService
//   dependía exclusivamente de 'collection' === 'cover'/'slider' para
//   hidratar el formulario de edición, así que la Portada y la Galería
//   Multimedia aparecían vacías al editar esos eventos, aunque sus archivos
//   sí existieran en BD y estuvieran correctamente vinculados.
//
//  REGLA DE CLASIFICACIÓN (la misma que ya usa este módulo en otras partes):
//   - El primer archivo adjunto al evento (order = 0, o NULL) → 'cover'.
//   - Cualquier archivo posterior (order > 0)                → 'slider'.
//   Solo se tocan filas con mediable_type = 'event' y collection IS NULL —
//   jamás se sobreescribe una clasificación ya explícita.
//
//  SEGURIDAD EN PRODUCCIÓN (Hostinger):
//   - Dos UPDATE...WHERE simples sobre una columna ya existente — sin
//     cambio de esquema, sin bloqueo de tabla prolongado aunque 'media'
//     esté en uso.
//   - Idempotente: volver a ejecutarla no modifica nada (el WHERE
//     collection IS NULL ya no encuentra filas tras la primera corrida).
//   - down() es intencionalmente un no-op: revertir a NULL reintroduciría
//     el bug de hidratación que esta migración corrige — no aporta ningún
//     valor real deshacerlo.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')
            ->where('mediable_type', 'event')
            ->whereNull('collection')
            ->where(function ($query) {
                $query->whereNull('order')->orWhere('order', 0);
            })
            ->update(['collection' => 'cover']);

        DB::table('media')
            ->where('mediable_type', 'event')
            ->whereNull('collection')
            ->where('order', '>', 0)
            ->update(['collection' => 'slider']);
    }

    public function down(): void
    {
        // No-op intencional — ver justificación arriba.
    }
};
