<?php

namespace App\Repositories\Events\EventDetail;

use App\Models\Event;

/**
 * Repositorio para la página de detalle de eventos.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD
 * para cargar un evento por su UUID.
 *
 * SEGURIDAD:
 *  - Toda condición WHERE usa Eloquent → PDO prepared statements.
 *  - select() explícito — solo columnas necesarias para el detalle.
 *  - Solo eventos 'published' son accesibles → sin exposición de drafts.
 *  - UUID validado externamente antes de llegar aquí (EventDetailService).
 *  - null silencioso: sin distinción entre "inexistente" y "no publicado"
 *    → previene enumeración de recursos.
 */
class EventDetailRepository
{
    // Columnas necesarias para la página de detalle
    // INCLUYE 'location' — agregada via migración add_location_to_events_table
    private const DETAIL_COLUMNS = [
        'id',
        'uuid',
        'name',
        'description',
        'location',      // dirección o nombre del lugar del evento
        'content',       // HTML del cuerpo del evento
        'category_id',
        'start_at',
        'end_at',
        'capacity_total',
        'status',
    ];

    /**
     * Busca un evento publicado por su UUID.
     * Retorna null si no existe o no está publicado.
     *
     * @param  string $uuid  UUID validado previamente por EventDetailService.
     * @return Event|null
     */
    public function findPublishedByUuid(string $uuid): ?Event
    {
        return Event::select(self::DETAIL_COLUMNS)
            ->where('status', 'published')
            ->where('uuid', $uuid)
            ->with([
                // Todos los archivos multimedia ordenados por order ASC.
                // El DTO separa portada (collection='cover', o el primer
                // ítem sin clasificar — ver EventDetailDto::resolverPortada())
                // de la galería, y clasifica el resto por mime type.
                'media' => fn ($q) => $q
                    ->select([
                        'id', 'mediable_id', 'mediable_type',
                        'path', 'disk', 'mime', 'size',
                        'alt', 'title', 'order', 'collection',
                    ])
                    ->orderBy('order'),

                // Solo nombre de categoría
                'category:id,name',

                // Solo estado de registros — sin datos personales
                'registrations' => fn ($q) => $q
                    ->select(['id', 'event_id', 'status'])
                    ->whereIn('status', ['registered', 'waitlist']),

                // Colaboradores invitados — eager loading anidado en 3
                // niveles (colaborador → socio → media del socio) en UNA
                // sola consulta batch por nivel, igual que ya hace
                // AdminEventsFormRepository::findForEdit() para el admin.
                // Sin esto, resolver el logo de cada colaborador en el DTO
                // dispararía una query adicional por fila (N+1).
                // orderBy('order') ya viene del propio Event::collaborators().
                'collaborators' => fn ($q) => $q->select([
                    'id', 'event_id', 'partner_id', 'is_custom',
                    'custom_name', 'custom_logo_path', 'participation_details', 'order',
                ]),
                'collaborators.partner:id,name',
                'collaborators.partner.media:id,mediable_id,mediable_type,collection,path,disk,mime',
            ])
            ->first();
    }
}
