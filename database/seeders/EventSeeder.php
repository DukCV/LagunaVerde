<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    private const DISK = 'public';

    private const ORDER_COVER = 0;

    // ════════════════════════════════════════════════════════════════════
    //  CATÁLOGO DE EVENTOS
    // ════════════════════════════════════════════════════════════════════

    private function eventData(): array
    {
        return [

            // ── Evento 1 — Taller ─────────────────────────────────────
            [
                'event' => [
                    'name' => 'Taller de Monitoreo de Calidad del Agua',
                    'description' => 'Aprende a medir parámetros clave del agua como pH, '
                                      .'oxígeno disuelto y turbidez usando equipos de campo.',
                    'location' => 'Laguna Verde, Muelle Principal — Libres, Puebla',
                    'category' => 'Taller',
                    'start_at' => '2026-07-12 09:00:00',
                    'end_at' => '2026-07-12 13:00:00',
                    'capacity_total' => 30,
                    'content' => '<p>El taller está diseñado para ciudadanos, '
                                      .'estudiantes y voluntarios interesados en aprender '
                                      .'técnicas básicas de monitoreo ambiental.</p>'
                                      .'<h2>Temario</h2>'
                                      .'<ul>'
                                      .'<li>Introducción a los parámetros fisicoquímicos</li>'
                                      .'<li>Uso de sondas y kits de análisis</li>'
                                      .'<li>Registro y análisis de datos en campo</li>'
                                      .'</ul>',
                    'status' => 'published',
                ],
                // Campos nuevos de AdminEventsForm — presencial, con inscripción
                // abierta y fecha de cierre explícita antes del inicio del evento.
                'formFields' => [
                    'published_at' => '2026-06-10 09:00:00',
                    'modality' => 'presencial',
                    'registration_enabled' => true,
                    'registration_start_at' => '2026-06-15 00:00:00',
                    'registration_end_at' => '2026-07-10 23:59:00',
                    'registration_no_end_date' => false,
                ],
                'media' => [
                    [
                        'path' => 'events/taller-monitoreo/portada.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 210000,
                        'title' => 'Participantes midiendo parámetros del agua',
                        'alt' => 'Voluntarios usando sondas digitales en la orilla de la laguna',
                        'order' => self::ORDER_COVER,
                    ],
                    [
                        'path' => 'events/taller-monitoreo/kit-analisis.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 145000,
                        'title' => 'Kit de análisis de agua',
                        'alt' => 'Kit portátil con reactivos y sondas para análisis de agua',
                        'order' => 1,
                    ],
                ],
            ],

            // ── Evento 2 — Jornada de limpieza ───────────────────────
            [
                'event' => [
                    'name' => 'Jornada de Limpieza y Reforestación Comunitaria',
                    'description' => 'Únete a nuestra jornada mensual de limpieza de orillas '
                                      .'y plantación de especies nativas. Abierto a todas las edades.',
                    'location' => 'Laguna Verde, Ribera Norte — Libres, Puebla',
                    'category' => 'Limpieza',
                    'start_at' => '2026-07-19 07:30:00',
                    'end_at' => '2026-07-19 12:00:00',
                    'capacity_total' => 120,
                    'content' => '<p>La jornada se divide en dos actividades: '
                                      .'limpieza de residuos y plantación de árboles nativos.</p>'
                                      .'<h2>¿Qué llevar?</h2>'
                                      .'<ul>'
                                      .'<li>Ropa que pueda ensuciarse</li>'
                                      .'<li>Botella de agua y snack</li>'
                                      .'<li>Protector solar</li>'
                                      .'</ul>',
                    'status' => 'published',
                ],
                // Presencial, inscripción abierta sin fecha de cierre explícita
                // (se usa la fecha de inicio del evento como límite — ver
                // Event::effectiveRegistrationDeadline()).
                'formFields' => [
                    'published_at' => '2026-06-12 09:00:00',
                    'modality' => 'presencial',
                    'registration_enabled' => true,
                    'registration_start_at' => '2026-06-20 00:00:00',
                    'registration_end_at' => null,
                    'registration_no_end_date' => true,
                ],
                'media' => [
                    [
                        'path' => 'events/jornada-limpieza/portada.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 235000,
                        'title' => 'Voluntarios en jornada de limpieza',
                        'alt' => 'Grupo de voluntarios recogiendo residuos en la laguna',
                        'order' => self::ORDER_COVER,
                    ],
                    [
                        'path' => 'events/jornada-limpieza/zona-reforestacion.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 178000,
                        'title' => 'Zona de reforestación',
                        'alt' => 'Área donde se realizará la plantación de árboles nativos',
                        'order' => 1,
                    ],
                    [
                        'path' => 'events/jornada-limpieza/resumen-edicion-anterior.mp4',
                        'mime' => 'video/mp4',
                        'size' => 1250000,
                        'title' => 'Resumen de edición anterior',
                        'alt' => 'Video resumen de la edición anterior de la jornada de limpieza',
                        'order' => 2,
                    ],
                ],
            ],

            // ── Evento 3 — Conferencia ───────────────────────────────
            [
                'event' => [
                    'name' => 'Conferencia: Ecosistemas Acuáticos y Cambio Climático',
                    'description' => 'El Dr. Héctor Villanueva presentará hallazgos sobre '
                                      .'el impacto del cambio climático en humedales urbanos.',
                    'location' => 'Auditorio Municipal — Libres, Puebla',
                    'category' => 'Conferencia',
                    'start_at' => '2026-08-05 18:00:00',
                    'end_at' => '2026-08-05 20:00:00',
                    'capacity_total' => 200,
                    'content' => '<p>La conferencia abordará tendencias climáticas '
                                      .'regionales e indicadores de salud del ecosistema.</p>'
                                      .'<p>Entrada libre con constancia de asistencia.</p>',
                    'status' => 'published',
                ],
                // Híbrido — ejercita tanto la dirección presencial como el
                // enlace virtual. Entrada libre: sin inscripción.
                'formFields' => [
                    'published_at' => '2026-07-01 09:00:00',
                    'modality' => 'hibrido',
                    'virtual_link' => 'https://meet.google.com/ccpc-ecosistemas-2026',
                    'registration_enabled' => false,
                    'registration_start_at' => null,
                    'registration_end_at' => null,
                    'registration_no_end_date' => false,
                ],
                'media' => [
                    [
                        'path' => 'events/conferencia-ecosistemas/portada.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 192000,
                        'title' => 'Dr. Héctor Villanueva — ponente principal',
                        'alt' => 'Fotografía del ponente frente a un ecosistema acuático',
                        'order' => self::ORDER_COVER,
                    ],
                    [
                        'path' => 'events/conferencia-ecosistemas/afiche-evento.jpg',
                        'mime' => 'image/jpeg',
                        'size' => 98000,
                        'title' => 'Afiche oficial de la conferencia',
                        'alt' => 'Cartel informativo con datos del evento',
                        'order' => 1,
                    ],
                ],
            ],

            // ── ➕ Evento 4 — descomenta para agregar más ─────────────
            // [
            //     'event' => [
            //         'name'           => 'Nombre del evento',
            //         'description'    => 'Descripción breve.',
            //         'location'       => 'Lugar — Ciudad, Estado',
            //         'category'       => 'Exposición',
            //         'start_at'       => '2026-09-01 10:00:00',
            //         'end_at'         => '2026-09-01 14:00:00',
            //         'capacity_total' => 50,
            //         'content'        => '<p>Contenido del evento.</p>',
            //         'status'         => 'published',
            //     ],
            //     'media' => [ ... ],
            // ],

        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ENTRY POINT
    // ════════════════════════════════════════════════════════════════════

    public function run(): void
    {
        $this->command->info('📅 Sembrando eventos...');

        foreach ($this->eventData() as $entry) {
            $this->seedEventEntry($entry);
        }

        $this->command->info('  ✓ Eventos listos.');
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Crea un evento (si no existe) y registra sus archivos multimedia.
     */
    private function seedEventEntry(array $entry): void
    {
        // Buscar el ID de la categoría usando DB::table() directamente.
        // Se evita el modelo Category para prevenir conflictos con scopes
        // o firmas de método que causaron el error "Expected 4, Found 2".
        $categoryId = DB::table('categories')
            ->where('type', '=', 'events')
            ->where('name', '=', $entry['event']['category'])
            ->value('id');

        if (! $categoryId) {
            $this->command->warn(
                "  ⚠ Categoría \"{$entry['event']['category']}\" no encontrada. "
                ."Omitiendo: \"{$entry['event']['name']}\""
            );

            return;
        }

        [$event, $created] = $this->findOrCreateEvent($entry['event'], (int) $categoryId);

        // Completa los campos nuevos de AdminEventsForm aunque el evento ya
        // existiera de una ejecución anterior del seeder.
        $this->seedNewFormFields($event, $entry['formFields'] ?? []);

        if (! $created) {
            $this->command->line("  → Ya existe: \"{$event->name}\"");

            return;
        }

        $this->command->line("  + Evento creado: \"{$event->name}\"");

        foreach ($entry['media'] as $mediaItem) {
            $this->attachMedia($event, $mediaItem);
        }
    }

    /**
     * Completa los campos del formulario de eventos (modalidad, ubicación,
     * inscripción, fecha de publicación) en un evento ya existente.
     *
     * IDEMPOTENTE Y NO DESTRUCTIVO: 'modality' se usa como centinela de
     * "¿ya se completaron estos campos?" — siempre queda definido tanto por
     * este seeder como por cualquier guardado real desde el formulario admin
     * (es obligatorio en LocationForm). Si ya tiene un valor, se asume que
     * el evento ya fue completado (por este seeder o por un administrador)
     * y no se sobrescribe nada.
     */
    private function seedNewFormFields(Event $event, array $fields): void
    {
        if ($fields === [] || $event->modality !== null) {
            return;
        }

        $event->forceFill($fields)->save();
    }

    /**
     * Busca o crea el evento en la BD.
     *
     * @return array{0: Event, 1: bool}
     */
    private function findOrCreateEvent(array $data, int $categoryId): array
    {
        $event = Event::firstOrCreate(
            ['name' => $data['name']],
            [
                'uuid' => (string) Str::uuid(),
                'description' => $data['description'],
                'location' => $data['location'] ?? null,
                'category_id' => $categoryId,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'capacity_total' => $data['capacity_total'],
                'content' => $data['content'] ?? null,
                'status' => $data['status'] ?? 'draft',
            ]
        );

        return [$event, $event->wasRecentlyCreated];
    }

    /**
     * Registra un archivo multimedia vinculado al evento.
     *
     * CLASIFICACIÓN ('collection'): el primer archivo adjunto (order = 0,
     * ver ORDER_COVER) se clasifica como 'cover'; cualquier archivo
     * posterior, como 'slider' — misma regla que usa el formulario admin al
     * guardar (AdminEventsFormService::guardar()) y al resolver el
     * fallback legacy en edición (AdminEventsFormService::resolverPortada()/
     * resolverGaleria()). Antes esta columna se omitía por completo, lo que
     * dejaba 'collection' en NULL y rompía la hidratación de Portada y
     * Galería al editar eventos sembrados.
     *
     * ⚠ Solo inserta el registro en BD — el archivo físico debe colocarse
     *   manualmente en: storage/app/public/{mediaItem['path']}
     */
    private function attachMedia(Event $event, array $mediaItem): void
    {
        $order = $mediaItem['order'] ?? self::ORDER_COVER;

        Media::firstOrCreate(
            [
                'mediable_type' => 'event',
                'mediable_id' => $event->id,
                'path' => $mediaItem['path'],
            ],
            [
                'disk' => self::DISK,
                'collection' => $order === self::ORDER_COVER ? 'cover' : 'slider',
                'mime' => $mediaItem['mime'],
                'size' => $mediaItem['size'],
                'title' => $mediaItem['title'] ?? null,
                'alt' => $mediaItem['alt'] ?? null,
                'order' => $order,
            ]
        );

        $type = str_starts_with($mediaItem['mime'], 'video/') ? 'video' : 'imagen';
        $this->command->line(
            "     ↳ [{$type} · order={$mediaItem['order']}] {$mediaItem['path']}"
        );
    }
}
