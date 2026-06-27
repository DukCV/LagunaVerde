<?php

namespace App\Livewire\Admin\Widgets;

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Widget de socios colaboradores del dashboard.
 *
 * Muestra los usuarios que poseen el rol 'Colaborador'.
 * En el modelo de negocio del proyecto, los socios se registran como usuarios
 * con este rol específico, lo que permite gestionar su acceso y visibilidad.
 *
 * RENDIMIENTO:
 *  - whereHas genera un EXISTS subquery que usa el índice de roles.name.
 *  - Solo se seleccionan columnas necesarias para la vista del widget.
 *  - Los roles se cargan en memoria con load() para el cheque de roles en vista
 *    (aunque en este widget solo se filtran por rol, no se muestran).
 */
class PartnersWidget extends Component
{
    private const ROL_COLABORADOR = 'Colaborador';
    private const LIMITE_WIDGET   = 5;

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.widgets.partners-widget', [
            'socios'    => $this->obtenerSocios(),
            'totalSocios' => $this->contarSocios(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS DE CONSULTA
    // ════════════════════════════════════════════════════════════════════

    /** Obtiene los primeros 5 usuarios con rol Colaborador */
    private function obtenerSocios(): Collection
    {
        return User::whereHas(
            'roles',
            fn ($q) => $q->where('name', self::ROL_COLABORADOR)
        )
            ->orderBy('name')
            ->take(self::LIMITE_WIDGET)
            ->get(['id', 'uuid', 'name', 'interest_area', 'profile_photo_path', 'country']);
    }

    /** Cuenta el total de socios para el enlace "Ver todos" */
    private function contarSocios(): int
    {
        return User::whereHas(
            'roles',
            fn ($q) => $q->where('name', self::ROL_COLABORADOR)
        )->count();
    }
}
