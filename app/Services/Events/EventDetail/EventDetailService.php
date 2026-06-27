<?php

namespace App\Services\Events\EventDetail;

use App\DTOs\Events\EventDetail\EventDetailDto;
use App\Repositories\Events\EventDetail\EventDetailRepository;

/**
 * Servicio para la página de detalle de un evento.
 *
 * RESPONSABILIDADES:
 *  - Validar el UUID antes de consultar la BD (primera línea de defensa).
 *  - Orquestar llamadas al repositorio.
 *  - Transformar el modelo Eloquent en DTO listo para la vista.
 *  - Ser la única dependencia que EventDetailPage inyecta.
 *
 * SEGURIDAD:
 *  - Validación de UUID con regex antes de cualquier query a BD.
 *  - Retorna null silencioso ante UUID inválido, inexistente o no publicado
 *    → sin distinción que permita enumerar recursos.
 */
class EventDetailService
{
    // Patrón UUID RFC-4122 — rechaza cualquier input malformado
    private const UUID_PATTERN =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly EventDetailRepository $repository
    ) {}

    /**
     * Retorna el DTO completo del evento o null si no es accesible.
     * El componente Livewire debe llamar abort(404) si recibe null.
     *
     * @param  string $uuid  UUID recibido del parámetro de ruta.
     * @return EventDetailDto|null
     */
    public function getDetail(string $uuid): ?EventDetailDto
    {
        // Validar formato antes de tocar la BD
        if (! $this->isValidUuid($uuid)) {
            return null;
        }

        $event = $this->repository->findPublishedByUuid($uuid);

        return $event ? EventDetailDto::fromModel($event) : null;
    }

    /** Valida que el string sea un UUID RFC-4122 bien formado. */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $uuid);
    }
}
