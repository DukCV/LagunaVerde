<?php

namespace App\Livewire\News\NewDetail;

use App\DTOs\NewsDetailDto;
use App\Services\NewsDetailService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: página de detalle de una noticia.
 *
 * RESPONSABILIDADES:
 *  - Recibir el UUID desde la ruta (web.php → blade → :uuid).
 *  - Validar y cargar la noticia via NewsDetailService.
 *  - Pasar el DTO a la vista y a los sub-componentes.
 *  - Redirigir con 404 si la noticia no existe o no está publicada.
 *
 * SEGURIDAD:
 *  - #[Locked] en $article → el cliente no puede alterar el DTO serializado.
 *  - UUID validado en el servicio antes de cualquier consulta a BD.
 *  - abort(404) silencioso: sin distinción entre "no existe" y "no publicada"
 *    → previene enumeración de recursos.
 *  - $newsId privado: el ID entero NUNCA se serializa en el estado Livewire.
 */
class NewsDetailPage extends Component
{
    /** UUID recibido del parámetro de ruta. */
    #[Locked]
    public string $uuid = '';

    /** DTO inmutable con todos los datos de la noticia. */
    #[Locked]
    public NewsDetailDto $article;

    /**
     * ID interno para operaciones que lo requieren (CommentSection).
     * Privado: nunca aparece en el estado serializado de Livewire.
     */
    private int $newsId;

    public function mount(string $uuid, NewsDetailService $service): void
    {
        $this->uuid = $uuid;

        $dto = $service->getDetail($uuid);

        // 404 silencioso: igual respuesta para UUID inválido, no publicada o inexistente
        abort_unless($dto !== null, 404);

        $this->article  = $dto;
        $this->newsId   = 0; // No almacenamos el ID en el estado público
    }

    public function render()
    {
        return view('livewire.news.new-detail.news-detail-page');
    }
}
