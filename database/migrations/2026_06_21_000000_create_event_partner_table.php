<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: event_partner (colaboradores invitados a un evento)
//
//  AUDITORÍA PREVIA:
//   No existía ninguna relación muchos-a-muchos entre 'events' y 'partners'.
//   Esta migración es de creación pura (CREATE TABLE): no modifica ni
//   elimina ninguna tabla ni columna existente — cero riesgo para los datos
//   ya presentes en producción. Ningún seeder existente (EventSeeder,
//   PartnerSeeder, DatabaseSeeder) escribe en esta tabla, así que tampoco
//   se ve afectado por este cambio.
//
//  POR QUÉ UN MODELO DEDICADO Y NO UN PIVOT IMPLÍCITO:
//   Esta tabla no es un pivot clásico de dos columnas: además de
//   'participation_details' y 'order', debe poder representar colaboradores
//   EXTERNOS que no existen en 'partners' (is_custom = true) — viven
//   únicamente atados a este evento, nunca se escriben en la tabla
//   'partners'. Un belongsToMany estándar exige que partner_id coincida con
//   partners.id, así que nunca devolvería las filas externas (partner_id
//   NULL). Por eso App\Models\EventCollaborator es un modelo Eloquent
//   normal con su propia PK, no un pivot — y App\Models\Event expone
//   'collaborators(): HasMany' en vez de 'partners(): BelongsToMany'.
//
//  LOGOTIPO DE COLABORADORES EXTERNOS:
//   'custom_logo_path' guarda la ruta en el disco 'public' directamente
//   (servida luego vía la ruta 'media.show', igual que cualquier otro
//   archivo de este proyecto) en vez de crear un registro en la tabla
//   polimórfica 'media': estos colaboradores no son un Partner real, así
//   que vincular un Media a un 'mediable' inexistente sería incorrecto.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_partner', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // Nulo cuando is_custom = true (colaborador externo, sin
            // registro en 'partners'). cascadeOnDelete: si el socio se
            // elimina del catálogo, su vínculo con este evento deja de
            // tener sentido — no hay archivo propio que limpiar aquí (el
            // logotipo del socio se gestiona por separado, en 'media').
            $table->foreignId('partner_id')
                  ->nullable()
                  ->constrained('partners')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->boolean('is_custom')->default(false);

            // Solo para colaboradores externos (is_custom = true). Nunca se
            // escriben en 'partners' — viven exclusivamente en esta fila,
            // atados a este evento.
            $table->string('custom_name', 150)->nullable();
            $table->string('custom_logo_path', 255)->nullable();

            // "Cómo será su participación" — opcional, válido para ambos
            // tipos de colaborador (de BD o externo).
            $table->string('participation_details', 300)->nullable();

            // Orden de aparición en la lista de colaboradores del evento.
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            // Evita vincular el mismo socio dos veces al mismo evento.
            // MySQL trata cada NULL como distinto dentro de un índice único,
            // así que esta restricción NO limita cuántas filas externas
            // (partner_id NULL) puede tener un mismo evento.
            $table->unique(['event_id', 'partner_id']);

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_partner');
    }
};
