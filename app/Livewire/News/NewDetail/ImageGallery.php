<?php

namespace App\Livewire\News\NewDetail;

use App\DTOs\MediaItemDto;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: galería de imágenes y videos.
 *
 * CORRECCIÓN: MediaItemDto es readonly y no implementa Wireable de forma
 * individual, por lo que Livewire no puede serializarla directamente
 * como elemento de un array de propiedades públicas.
 * Solución: convertir cada MediaItemDto a array plano en mount() para
 * que Livewire solo almacene primitivos serializables.
 */
class ImageGallery extends Component
{
    /**
     * Array de arrays planos (no objetos MediaItemDto).
     * Cada elemento tiene las claves: url, mime, title, alt, size, order,
     * isImage, isVideo, isDocument.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $items = [];

    #[Locked]
    public string $newsTitle = '';

    public function mount(array $items = [], string $newsTitle = ''): void
    {
        // Convertir cada elemento a array plano.
        // Acepta tanto MediaItemDto como arrays ya serializados.
        $this->items = array_values(array_map(
            fn ($item) => $item instanceof MediaItemDto
                ? $this->dtoToArray($item)
                : (array) $item,
            $items
        ));

        $this->newsTitle = mb_substr(strip_tags($newsTitle), 0, 220);
    }

    public function render()
    {
        return view('livewire.news.new-detail.image-gallery');
    }

    // ── Helper ───────────────────────────────────────────────────────────

    /**
     * Convierte un MediaItemDto en un array plano de primitivos.
     * Todos los tipos son serializables por Livewire (string, int, bool).
     */
    private function dtoToArray(MediaItemDto $dto): array
    {
        return [
            'url'        => $dto->url,
            'mime'       => $dto->mime,
            'title'      => $dto->title,
            'alt'        => $dto->alt,
            'size'       => $dto->size,
            'order'      => $dto->order,
            'isImage'    => $dto->isImage,
            'isVideo'    => $dto->isVideo,
            'isDocument' => $dto->isDocument,
        ];
    }
}
