<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope: filtra automáticamente las noticias para el lado público.
 *
 * PROPÓSITO:
 *  - Actúa como red de seguridad estricta sobre el modelo News.
 *  - Garantiza que NINGUNA noticia que no sea 'published' pueda escaparse
 *    al lado público, incluso si un desarrollador olvida llamar ->published().
 *  - Complementa (no reemplaza) el local scope scopePublished() del modelo.
 *
 * CONDICIONES QUE APLICA:
 *  1. status = 'published'     → excluye boradores, archivadas y deshabilitadas.
 *  2. published_at IS NOT NULL → excluye noticias sin fecha de publicación.
 *  3. published_at <= now()    → excluye publicaciones programadas a futuro.
 *
 * CUÁNDO DESACTIVARLO:
 *  - Panel de administración: requiere ver TODOS los estados.
 *    Usar News::withoutGlobalScope(PublishedScope::class) o
 *    News::withoutGlobalScopes() en los repositorios/servicios del admin.
 *
 * REFERENCIA:
 *  - Implementa Illuminate\Database\Eloquent\Scope (contrato de Laravel).
 *  - Registrado en News::booted() vía static::addGlobalScope().
 */
class PublishedScope implements Scope
{
    /**
     * Aplica el scope a un Eloquent Builder dado.
     *
     * Laravel llama a este método automáticamente en cada consulta
     * que parta de News::... (salvo que se desactive explícitamente).
     *
     * @param  Builder<\App\Models\News> $builder  Constructor de consulta Eloquent.
     * @param  Model                    $model     Instancia del modelo (News).
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Condición 1: solo noticias en estado 'published'.
        // Excluye: 'draft', 'archived', 'disabled'.
        $builder->where('status', 'published')

        // Condición 2: la fecha de publicación debe estar definida.
        // Noticias sin published_at no están listas para el público.
                ->whereNotNull('published_at')

        // Condición 3: la fecha de publicación debe ser pasada o presente.
        // Permite publicaciones programadas sin necesidad de un job externo.
                ->where('published_at', '<=', now());
    }
}
