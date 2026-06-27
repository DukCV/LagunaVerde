<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivote enriquecido de la tabla role_user.
 *
 * Además de unir User y Role, almacena atributos propios de ESA asignación
 * de rol concreta (no del usuario ni del rol en general), todos relevantes
 * solo cuando el rol es 'Administrador':
 *  - position     : puesto del usuario en esta asignación (ej. "Director General").
 *  - permissions  : permisos granulares, contra la lista blanca de
 *                   AdminRoleService::PERMISOS_DISPONIBLES.
 *  - public_bio        : semblanza pública mostrada en "Quiénes Somos" → "Nuestro Equipo".
 *  - social_links      : enlaces a redes sociales para esa misma sección — mismas
 *                        claves que Partner (website/instagram/facebook/twitter/
 *                        linkedin/youtube) para reutilizar <x-social-links> sin cambios.
 *  - show_in_about_us  : si el perfil se muestra públicamente en esa sección —
 *                        controlado por el propio usuario desde "Mi Perfil"
 *                        (App\Livewire\Admin\MyProfile). Falso por defecto.
 *
 * IMPORTANTE: al registrar esta clase con ->using() en User::roles()/
 * Role::users(), BelongsToMany::sync()/attach() SÍ pasan por este modelo
 * (vía $pivot->fill($attributes)->save()) en lugar de un INSERT/UPDATE SQL
 * directo. Por lo tanto 'permissions'/'social_links' deben pasarse como
 * array PHP plano al sincronizar — el cast 'array' de abajo se encarga del
 * json_encode() tanto al guardar como al leer. Pre-codificarlos a JSON antes
 * de sync() produce doble codificación. 'show_in_about_us' es un booleano
 * simple, sin ese riesgo.
 */
class RoleUser extends Pivot
{
    protected $table = 'role_user';

    protected $fillable = [
        'user_id', 'role_id', 'position', 'permissions',
        'public_bio', 'social_links', 'show_in_about_us',
    ];

    protected function casts(): array
    {
        return [
            'permissions'       => 'array',
            'social_links'      => 'array',
            'show_in_about_us'  => 'boolean',
        ];
    }
}
