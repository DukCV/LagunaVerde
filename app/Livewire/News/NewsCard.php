<?php

namespace App\Livewire\News;

use App\DTOs\NewsCardDto;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: tarjeta de resumen de una noticia.
 *
 * RESPONSABILIDADES:
 *  - Renderizar el resumen de una noticia a partir de un NewsCardDto.
 *  - Gestionar el toggle de documentos adjuntos (único estado local).
 *  - No accede a la BD ni conoce el modelo News.
 *
 * SEGURIDAD:
 *  - #[Locked] en $article impide que el cliente modifique el DTO
 *    en peticiones posteriores de Livewire (protección contra tampering).
 *  - #[Locked] en $featured por la misma razón.
 *  - La navegación al detalle se hace con <a href> (UUID en URL),
 *    no con wire:click, para que funcione sin JS y sea indexable.
 *  - Toda salida en la vista usa {{ }} → auto-escaping XSS.
 */
class NewsCard extends Component
{
    /** DTO inmutable con los datos de la noticia. */
    #[Locked]
    public NewsCardDto $article;

    /** Indica si esta tarjeta debe mostrarse en formato destacado. */
    #[Locked]
    public bool $featured = false;

    /** Estado local: panel de documentos abierto/cerrado. */
    public bool $showDocs = false;

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES
    // ════════════════════════════════════════════════════════════════════

    public function toggleDocs(): void
    {
        $this->showDocs = ! $this->showDocs;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.news.news-card');
    }
}
