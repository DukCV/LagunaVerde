<?php

namespace App\DTOs\Multimedia;

use App\Models\Event;
use App\Models\News;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;

/**
 * DTO inmutable que representa un álbum de la galería multimedia.
 *
 * Un álbum es la abstracción de una Noticia o Evento que contiene
 * uno o más archivos multimedia.  Oculta el tipo concreto del modelo
 * Eloquent fuente, exponiendo sólo los datos necesarios para la vista.
 *
 * SEGURIDAD:
 *  - readonly → inmutable después de la construcción.
 *  - Textos saneados (strip_tags, mb_substr) antes de exponerse.
 *  - El ID entero nunca se expone; sólo el UUID se usa en URLs y wire:key.
 *  - La ruta de detalle se genera con route() → URLs correctas sin concatenación manual.
 */
readonly class AlbumDto
{
    // ── Longitudes máximas para sanitización ─────────────────────────────
    private const MAX_TITLE_LENGTH       = 300;
    private const MAX_DESCRIPTION_LENGTH = 500;

    public function __construct(
        /** UUID público del modelo origen (News o Event) */
        public string $uuid,
        /** Título visible del álbum */
        public string $title,
        /** Descripción breve (summary para News, description para Event) */
        public string $description,
        /** Etiqueta de categoría: "noticia" o "evento" */
        public string $category,
        /** Fecha formateada para mostrar en la UI */
        public string $date,
        /** URL de la imagen de portada (primer elemento de media con isImage=true) */
        public string $coverImageUrl,
        /** Ruta nombrada de detalle para el botón "Ver detalles" */
        public string $detailRoute,
        /** Nombre de la ruta (para href en Blade) */
        public string $detailRouteName,
        /** Cantidad total de archivos multimedia asociados */
        public int    $mediaCount,
        /** Colección de DTOs de items de media */
        public array  $mediaItems,   // MediaAlbumItemDto[]
    ) {}

    /**
     * Fábrica estática que construye el DTO desde un modelo News o Event.
     *
     * La colección $model->media debe estar ya cargada (eager load)
     * para evitar N+1 queries.
     *
     * @param  News|Event  $model  Modelo Eloquent con relación media pre-cargada.
     */
    public static function fromModel(Model $model): self
    {
        // Determinar si la fuente es una Noticia o un Evento
        $isNews = $model instanceof News;

        // Mapear campos específicos de cada modelo
        $title       = $isNews ? $model->title : $model->name;
        $description = $isNews ? ($model->summary ?? '') : ($model->description ?? '');
        $category    = $isNews ? 'noticia' : 'evento';

        // Formatear fecha según el campo disponible en cada modelo
        $date = self::formatDate(
            $isNews ? $model->published_at : $model->start_at
        );

        // Ruta de detalle por tipo de modelo
        $routeName  = $isNews ? 'news.show' : 'events.show';
        $detailRoute = route($routeName, $model->uuid);

        // Construir DTOs de items de media desde la relación ya cargada
        $mediaItems = $model->media
            ->map(fn ($m) => MediaAlbumItemDto::fromModel($m))
            ->values()
            ->all();

        // Imagen de portada: primer item de tipo imagen; fallback a primer item
        $cover = collect($mediaItems)->first(fn ($item) => $item->isImage)
            ?? collect($mediaItems)->first();

        return new self(
            uuid:            $model->uuid,
            title:           self::sanitize($title, self::MAX_TITLE_LENGTH),
            description:     self::sanitize($description, self::MAX_DESCRIPTION_LENGTH),
            category:        $category,
            date:            $date,
            coverImageUrl:   $cover?->url ?? '',
            detailRoute:     $detailRoute,
            detailRouteName: $routeName,
            mediaCount:      count($mediaItems),
            mediaItems:      $mediaItems,
        );
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    /** Formatea una fecha Carbon o null a una cadena legible en español */
    private static function formatDate(Carbon|CarbonImmutable|null $date): string
    {
        if ($date === null) {
            return '—';
        }

        // Formato: "15 Nov 2025" (sin coma, mes abreviado en español)
        return $date->locale('es')->isoFormat('D MMM YYYY');
    }

    /** Elimina HTML y limita la longitud de un texto */
    private static function sanitize(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
