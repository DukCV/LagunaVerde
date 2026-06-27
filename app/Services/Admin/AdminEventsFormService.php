<?php

namespace App\Services\Admin;

use App\DTOs\Admin\PartnerPickerItemDto;
use App\Models\Event;
use App\Models\EventCollaborator;
use App\Models\Media;
use App\Models\Partner;
use App\Repositories\Admin\AdminEventsFormRepository;
use App\Support\RichText\SanitizesRichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio de orquestación para el formulario de creación/edición de eventos.
 *
 * RESPONSABILIDADES:
 *  - Preparar los datos de carga inicial del formulario (modo edición),
 *    incluyendo la lista unificada de medios del slider (mediaItems).
 *  - Ejecutar el guardado completo dentro de una transacción DB atómica:
 *    datos del evento, portada, eliminación/inserción/reordenamiento del
 *    slider multimedia.
 *  - Sanitizar el contenido HTML del editor Trix (vía SanitizesRichText).
 *  - Delegar la persistencia exclusivamente al AdminEventsFormRepository.
 *
 * ARQUITECTURA DE TRANSACCIONES (idéntica a NewsFormService):
 *  Las eliminaciones de archivos se recopilan en $archivosAEliminar DENTRO
 *  de la transacción de BD, pero solo se borran del disco DESPUÉS de que la
 *  transacción confirme con éxito — evita huérfanos en Storage si la BD
 *  falla, y evita perder archivos recuperables si el borrado se revierte.
 */
class AdminEventsFormService
{
    use SanitizesRichText;

    private const MAX_COLABORADORES_SUGERIDOS = 12;

    public function __construct(
        private readonly AdminEventsFormRepository $repository,
        private readonly AdminPartnersService $partnersService,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  LECTURA — DATOS PARA EL FORMULARIO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve el array de [id => nombre] de categorías disponibles.
     *
     * @return array<int, string>
     */
    public function obtenerCategorias(): array
    {
        return $this->repository->availableCategories()
            ->pluck('name', 'id')
            ->toArray();
    }

    /** Tipos de socio disponibles para el filtro del selector de colaboradores. */
    public function obtenerTiposColaborador(): array
    {
        return Partner::TYPES;
    }

    /**
     * Socios activos disponibles para invitar como colaboradores del
     * evento, ya filtrados por búsqueda/tipo y excluyendo los ya añadidos.
     * Delegado íntegramente a AdminPartnersService — este servicio nunca
     * consulta Partner ni su repositorio directamente.
     *
     * @param int[] $excludeIds
     * @return array<int, PartnerPickerItemDto>
     */
    public function buscarColaboradoresDisponibles(string $search, string $type, array $excludeIds): array
    {
        return $this->partnersService->searchActiveForPicker(
            $search,
            $type,
            $excludeIds,
            self::MAX_COLABORADORES_SUGERIDOS,
        );
    }

    /**
     * Revalida y resuelve un socio por su ID antes de añadirlo a la lista
     * de colaboradores del evento — nunca se confía en los datos (nombre,
     * logo) ya renderizados en el cliente al momento del clic en "Agregar".
     */
    public function buscarColaboradorParaAgregar(int $partnerId): ?PartnerPickerItemDto
    {
        return $this->partnersService->findActiveForPicker($partnerId);
    }

    /**
     * Carga todos los datos del evento para pre-rellenar el formulario de
     * edición, en la forma exacta que espera cada Form object de EventForm.
     *
     * CON FALLBACK LEGACY (igual que NewsFormService::obtenerParaEdicion()):
     * EventSeeder::attachMedia() crea los registros de Media sin establecer
     * 'collection' (solo fija 'order', usando 0 para la portada). Eso deja
     * 'collection' en NULL para todo evento sembrado, así que depender
     * exclusivamente de 'collection' === 'cover'/'slider' (como hacía esta
     * clase antes) deja la portada y la galería vacías al editar esos
     * eventos, aunque sus archivos sí existan y estén vinculados. Por eso
     * resolverPortada()/resolverGaleria() reproducen el mismo criterio de
     * respaldo que ya usa NewsFormService para registros sin clasificar.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function obtenerParaEdicion(int $id): array
    {
        $event = $this->repository->findForEdit($id);

        $todosLosMedias = $event->media;

        $cover = $this->resolverPortada($todosLosMedias);

        $mediaItems = $this->resolverGaleria($todosLosMedias, $cover)
            ->values()
            ->map(fn (Media $m, int $indice) => [
                'key'      => 'existing-' . $m->id,
                'source'   => 'existing',
                'id'       => $m->id,
                'tmpIndex' => null,
                'url'      => $m->url(),
                'tipo'     => $m->isImage() ? 'imagen' : 'video',
                'nombre'   => $m->title ?? basename($m->path),
                'order'    => $indice,
            ])
            ->values()
            ->toArray();

        return [
            'generalInfo' => [
                'name'        => $event->name,
                'description' => $event->description,
                'content'     => $event->content ?? '',
                'categoryId'  => (string) $event->category_id,
            ],
            'schedule' => [
                'startAt'     => optional($event->start_at)->format('Y-m-d\TH:i') ?? '',
                'endAt'       => optional($event->end_at)->format('Y-m-d\TH:i') ?? '',
                'publishedAt' => optional($event->published_at)->format('Y-m-d') ?? '',
            ],
            'location' => [
                'modality'    => $event->modality ?? '',
                'location'    => $event->location ?? '',
                'virtualLink' => $event->virtual_link ?? '',
            ],
            'registration' => [
                'registrationEnabled'   => $event->registration_enabled,
                'unlimitedCapacity'     => $event->capacity_total === 0,
                'capacityTotal'         => $event->capacity_total,
                'registrationStartAt'   => optional($event->registration_start_at)->format('Y-m-d') ?? '',
                'registrationEndAt'     => optional($event->registration_end_at)->format('Y-m-d') ?? '',
                'registrationNoEndDate' => $event->registration_no_end_date,
            ],
            'coverUrl'   => $cover?->url(),
            'mediaItems' => $mediaItems,
            'status'     => $event->status,

            'collaborators' => [
                // Convención igual a 'unlimitedCapacity' en RegistrationForm:
                // no existe una columna "con colaboradores" en 'events' — el
                // interruptor es un valor derivado de "¿tiene filas en
                // event_partner?", solo para la interfaz.
                'withCollaborators' => $event->collaborators->isNotEmpty(),
                'items'             => $this->resolverColaboradores($event),
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — GUARDADO PRINCIPAL
    // ════════════════════════════════════════════════════════════════════

    /**
     * Crea o actualiza un evento con todos sus archivos asociados.
     *
     * @param int|null $eventId null para crear, int para editar
     * @param array $datos ['estado', 'generalInfo', 'schedule', 'location', 'registration']
     * @param UploadedFile|null $cover nueva imagen de portada (puede ser null)
     * @param bool $removeCover true si el admin quitó la portada actual
     * @param array $mediaPlan lista ORDENADA del slider final, cada ítem:
     *   ['tipo' => 'existente', 'id' => int] o ['tipo' => 'nuevo', 'archivo' => UploadedFile]
     * @param int[] $mediaIdsAEliminar IDs de Media del slider a borrar
     * @param array $collaboratorsPlan lista ORDENADA de colaboradores finales (vacía si
     *   "Con colaboradores" está desactivado), cada ítem:
     *   ['source' => 'partner', 'partnerId' => int, 'participationDetails' => string] o
     *   ['source' => 'custom', 'customName' => string, 'logo' => null|['tipo'=>'existente','path'=>string]|['tipo'=>'nuevo','archivo'=>UploadedFile], 'participationDetails' => string]
     *
     * @throws \Throwable si la transacción de BD falla
     */
    public function guardar(
        ?int $eventId,
        array $datos,
        ?UploadedFile $cover,
        bool $removeCover,
        array $mediaPlan,
        array $mediaIdsAEliminar,
        array $collaboratorsPlan,
    ): Event {
        // Archivos físicos a eliminar DESPUÉS de que la transacción tenga éxito
        $archivosAEliminar = [];

        $event = DB::transaction(function () use (
            $eventId, $datos, $cover, $removeCover, $mediaPlan, $mediaIdsAEliminar, $collaboratorsPlan, &$archivosAEliminar
        ) {
            $general      = $datos['generalInfo'];
            $schedule     = $datos['schedule'];
            $location     = $datos['location'];
            $registration = $datos['registration'];

            // Convención ya establecida en AdminEventItemDto: 0 = ilimitado.
            $capacityTotal = $registration['unlimitedCapacity']
                ? 0
                : (int) $registration['capacityTotal'];

            $atributos = [
                'name'        => trim($general['name']),
                // Nunca NULL: la columna 'description' no admite NULL.
                // En borrador sin descripción se persiste cadena vacía.
                'description' => trim((string) $general['description']),
                'content'     => $this->sanitizarContenido($general['content']),
                'category_id' => (int) $general['categoryId'],
                'status'      => $datos['estado'],

                'start_at'     => Carbon::parse($schedule['startAt']),
                'end_at'       => Carbon::parse($schedule['endAt']),
                'published_at' => $schedule['publishedAt'] !== ''
                    ? Carbon::parse($schedule['publishedAt'])
                    : null,

                'modality'     => $location['modality'],
                'location'     => $location['location'] !== '' ? $location['location'] : null,
                'virtual_link' => $location['virtualLink'] !== '' ? $location['virtualLink'] : null,

                'capacity_total'           => $capacityTotal,
                'registration_enabled'     => $registration['registrationEnabled'],
                'registration_start_at'    => $registration['registrationStartAt'] !== ''
                    ? Carbon::parse($registration['registrationStartAt'])
                    : null,
                'registration_end_at'      => $registration['registrationEndAt'] !== ''
                    ? Carbon::parse($registration['registrationEndAt'])
                    : null,
                'registration_no_end_date' => $registration['registrationNoEndDate'],
            ];

            if ($eventId === null) {
                $event = $this->repository->create($atributos);
            } else {
                $this->repository->update($eventId, $atributos);
                $event = $this->repository->findForEdit($eventId);

                // Sana datos legacy ANTES de las operaciones por 'collection'
                // de más abajo (recopilarRutasDeColeccion/deleteMediaByCollection,
                // ambas sobre 'cover') — ver normalizarColeccionLegacy().
                $this->normalizarColeccionLegacy($event);
            }

            // ── Portada ────────────────────────────────────────────────
            if ($removeCover || $cover !== null) {
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->recopilarRutasDeColeccion($event->id, 'cover')
                );
                $this->repository->deleteMediaByCollection($event->id, 'cover');
            }

            if ($cover !== null) {
                $ruta = $cover->store('events/covers', 'public');
                $event->media()->create([
                    'collection' => 'cover',
                    'disk'       => 'public',
                    'path'       => $ruta,
                    'mime'       => $cover->getMimeType(),
                    'size'       => $cover->getSize(),
                    'title'      => $atributos['name'],
                    'alt'        => $atributos['name'],
                    'order'      => 0,
                ]);
            }

            // ── Slider: eliminar marcados ─────────────────────────────
            if (! empty($mediaIdsAEliminar)) {
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->recopilarRutas($mediaIdsAEliminar)
                );
                $this->repository->deleteMediaByIds($mediaIdsAEliminar);
            }

            // ── Slider: insertar nuevos archivos y fijar el orden final ──
            // El orden final (incluyendo el reordenamiento por arrastrar y
            // soltar) se persiste en UNA sola pasada, en el momento del
            // guardado — nunca durante el arrastre en sí.
            $idAOrden = [];
            foreach ($mediaPlan as $posicion => $item) {
                if ($item['tipo'] === 'existente') {
                    $idAOrden[$item['id']] = $posicion;
                    continue;
                }

                $archivo = $item['archivo'];
                $ruta    = $archivo->store('events/media', 'public');
                $event->media()->create([
                    'collection' => 'slider',
                    'disk'       => 'public',
                    'path'       => $ruta,
                    'mime'       => $archivo->getMimeType(),
                    'size'       => $archivo->getSize(),
                    'title'      => $archivo->getClientOriginalName(),
                    'alt'        => $archivo->getClientOriginalName(),
                    'order'      => $posicion,
                ]);
            }

            if (! empty($idAOrden)) {
                $this->repository->updateMediaOrder($idAOrden);
            }

            // ── Colaboradores invitados: reemplazo total en cada guardado ──
            // El tamaño de esta lista es siempre pequeño por evento, así que
            // borrar todo e insertar de nuevo (en vez de calcular un diff
            // fila a fila) es más simple y igual de seguro — mismo criterio
            // que ya usa este método para el slider multimedia al reordenar.
            $rutasLogoExistentes   = $this->repository->customCollaboratorLogoPaths($event->id);
            $rutasLogoReutilizadas = [];

            $filasColaboradores = [];
            foreach ($collaboratorsPlan as $posicion => $item) {
                $rutaLogo = null;

                if ($item['source'] === 'custom' && $item['logo'] !== null) {
                    if ($item['logo']['tipo'] === 'nuevo') {
                        $rutaLogo = $item['logo']['archivo']->store('events/collaborators', 'public');
                    } else {
                        $rutaLogo = $item['logo']['path'];
                        $rutasLogoReutilizadas[] = $rutaLogo;
                    }
                }

                $filasColaboradores[] = [
                    'event_id'              => $event->id,
                    'partner_id'            => $item['source'] === 'partner' ? $item['partnerId'] : null,
                    'is_custom'             => $item['source'] === 'custom',
                    'custom_name'           => $item['source'] === 'custom' ? $item['customName'] : null,
                    'custom_logo_path'      => $rutaLogo,
                    'participation_details' => $item['participationDetails'] !== ''
                        ? $item['participationDetails']
                        : null,
                    'order'      => $posicion,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Logos personalizados que ya no aparecen en el plan final
            // (colaborador eliminado, o su logo fue remplazado por uno
            // nuevo) — se borran del disco tras confirmar la transacción.
            $archivosAEliminar = array_merge(
                $archivosAEliminar,
                array_map(
                    fn (string $ruta) => ['disk' => 'public', 'path' => $ruta],
                    array_diff($rutasLogoExistentes, $rutasLogoReutilizadas)
                )
            );

            $this->repository->deleteCollaborators($event->id);
            $this->repository->insertCollaborators($filasColaboradores);

            return $event;
        });

        // ── Eliminar archivos físicos TRAS confirmar la transacción ───────
        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $event;
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve un array de ['disk' => ..., 'path' => ...] para los IDs dados.
     *
     * @param  int[] $ids
     * @return array<int, array{disk: string, path: string}>
     */
    private function recopilarRutas(array $ids): array
    {
        return Media::whereIn('id', $ids)
            ->get(['disk', 'path'])
            ->map(fn (Media $m) => ['disk' => $m->disk, 'path' => $m->path])
            ->toArray();
    }

    /**
     * Devuelve rutas de todos los medios de una colección para un evento.
     *
     * @return array<int, array{disk: string, path: string}>
     */
    private function recopilarRutasDeColeccion(int $eventId, string $collection): array
    {
        return Media::where('mediable_type', (new Event)->getMorphClass())
            ->where('mediable_id', $eventId)
            ->where('collection', $collection)
            ->get(['disk', 'path'])
            ->map(fn (Media $m) => ['disk' => $m->disk, 'path' => $m->path])
            ->toArray();
    }

    /**
     * Transforma las filas de event_partner (ya cargadas vía eager loading
     * en findForEdit(), con 'partner.media' incluido) a la forma exacta que
     * espera EventForm::$selectedCollaborators.
     *
     * 'logoUrl' para colaboradores externos se construye con la misma ruta
     * 'media.show' que Media::url() — custom_logo_path no vive en la tabla
     * polimórfica 'media' (ver docblock de la migración), así que no hay un
     * modelo Media del que pedir la URL.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolverColaboradores(Event $event): array
    {
        return $event->collaborators
            ->map(function (EventCollaborator $fila) {
                if ($fila->is_custom) {
                    return [
                        'key'                    => 'custom-' . $fila->id,
                        'source'                 => 'custom',
                        'partnerId'              => null,
                        'name'                   => $fila->custom_name ?? '',
                        'logoUrl'                => $fila->custom_logo_path !== null
                            ? route('media.show', ['path' => $fila->custom_logo_path])
                            : null,
                        'customLogoExistingPath' => $fila->custom_logo_path,
                        'tmpLogoIndex'           => null,
                        'participationDetails'   => $fila->participation_details ?? '',
                        'order'                  => $fila->order,
                    ];
                }

                $logo = $fila->partner?->media->firstWhere('collection', 'logo');

                return [
                    'key'                    => 'partner-' . $fila->partner_id,
                    'source'                 => 'partner',
                    'partnerId'              => $fila->partner_id,
                    'name'                   => $fila->partner?->name ?? '',
                    'logoUrl'                => $logo?->url(),
                    'customLogoExistingPath' => null,
                    'tmpLogoIndex'           => null,
                    'participationDetails'   => $fila->participation_details ?? '',
                    'order'                  => $fila->order,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resuelve la portada de $todosLosMedias (ya cargada vía eager loading,
     * sin queries adicionales): preferencia 'collection' === 'cover'; si no
     * hay ninguna, recae en la imagen sin clasificar (collection === null)
     * con el 'order' más bajo — la convención de este módulo es guardar la
     * portada en order=0 (ver guardar() más arriba y EventSeeder::ORDER_COVER).
     */
    private function resolverPortada(Collection $todosLosMedias): ?Media
    {
        return $todosLosMedias->firstWhere('collection', 'cover')
            ?? $todosLosMedias
                ->filter(fn (Media $m) => $m->collection === null && $m->isImage())
                ->sortBy('order')
                ->first();
    }

    /**
     * Resuelve la galería/slider de $todosLosMedias, ordenada por 'order':
     * preferencia 'collection' === 'slider'; además incluye, por
     * compatibilidad legacy, cualquier imagen o vídeo sin clasificar que no
     * sea ya la portada resuelta por resolverPortada() (evita duplicar el
     * mismo archivo como portada y como ítem de galería a la vez).
     */
    private function resolverGaleria(Collection $todosLosMedias, ?Media $portada): Collection
    {
        return $todosLosMedias
            ->filter(fn (Media $m) => $m !== $portada && (
                $m->collection === 'slider'
                || ($m->collection === null && ($m->isImage() || $m->isVideo()))
            ))
            ->sortBy('order');
    }

    /**
     * Normaliza 'collection' a un valor real para medios legacy de $event
     * (creados antes de existir esa columna, ej. EventSeeder::attachMedia()
     * antes de su corrección — ver migración backfill_event_media_collection).
     *
     * POR QUÉ ES NECESARIO: resolverPortada()/resolverGaleria() ya toleran
     * collection === null al LEER, pero las operaciones de escritura más
     * abajo en guardar() (recopilarRutasDeColeccion/deleteMediaByCollection)
     * filtran de forma estricta por collection = 'cover'. Sin esta
     * normalización, remplazar la portada de un evento legacy no tocaría la
     * fila vieja (collection NULL) — quedaría huérfana en BD y, peor,
     * resolverGaleria() la mostraría como ítem de galería en la siguiente
     * carga del formulario, porque ya no sería "la portada" resuelta.
     *
     * Se ejecuta dentro de la misma transacción de guardar(), antes de
     * cualquier operación sobre 'collection' — así esas operaciones
     * siempre encuentran la fila correcta, sin importar si esta es la
     * primera vez que se edita el evento desde que existe 'collection'.
     */
    private function normalizarColeccionLegacy(Event $event): void
    {
        $todosLosMedias = $event->media;

        if ($todosLosMedias->doesntContain(fn (Media $m) => $m->collection === null)) {
            return; // Camino feliz: nada legacy que normalizar, sin tocar la BD.
        }

        $portada = $this->resolverPortada($todosLosMedias);

        if ($portada !== null && $portada->collection === null) {
            Media::where('id', $portada->id)->update(['collection' => 'cover']);
            $portada->collection = 'cover'; // refleja el cambio en la instancia ya cargada
        }

        $idsGaleriaLegacy = $this->resolverGaleria($todosLosMedias, $portada)
            ->filter(fn (Media $m) => $m->collection === null)
            ->pluck('id');

        if ($idsGaleriaLegacy->isNotEmpty()) {
            Media::whereIn('id', $idsGaleriaLegacy)->update(['collection' => 'slider']);
        }
    }
}
