<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',     // 'cover' | 'slider' | 'document' | null (registros legacy)
        'disk',
        'path',
        'mime',
        'size',
        'title',
        'alt',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'size'  => 'integer',
            'order' => 'integer',
        ];
    }

    // ── Relación polimórfica ─────────────────────────────────────────────

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * URL pública del archivo.
     *
     * No genera la URL vía el enlace simbólico público/storage (mismo
     * motivo documentado en App\Http\Controllers\MediaController: poco
     * fiable en hosting compartido como Hostinger). Se sirve en su lugar a
     * través de la ruta 'media.show', que lee el archivo directamente del
     * disco sin depender de ese enlace.
     */
    public function url(): string
    {
        // Recursos externos (ej. enlaces de YouTube guardados como 'path')
        // ya son una URL completa: se devuelven tal cual.
        if (str_starts_with($this->path, 'http://') || str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return route('media.show', ['path' => $this->path]);
    }

    /** Devuelve true si el MIME es imagen */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** Devuelve true si el MIME es vídeo */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }

    /** Devuelve true si el archivo es un documento descargable (no imagen ni vídeo) */
    public function isDocument(): bool
    {
        return ! $this->isImage() && ! $this->isVideo();
    }
}
