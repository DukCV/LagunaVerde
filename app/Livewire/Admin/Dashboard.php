<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Componente raíz del Panel de Administración.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar la sección activa de navegación del dashboard.
 *  - Controlar el estado visual del sidebar (abierto/colapsado).
 *  - Actuar como contenedor que monta los widgets hijos según la sección.
 *  - Proveer acceso al usuario autenticado para el encabezado.
 *
 * SEGURIDAD:
 *  - Protegido por middleware 'admin' (EnsureAdministrator) en la ruta.
 *  - Las secciones válidas se validan contra lista blanca antes de aceptarlas.
 *  - Toda salida de datos usa {{ }} para escape XSS automático de Blade.
 */
#[Layout('layouts.admin')]
#[Title('Panel de Administración')]
class Dashboard extends Component
{
    // ── Lista blanca de secciones válidas ────────────────────────────────
    private const SECCIONES_VALIDAS = [
        'inicio', 'noticias', 'eventos', 'multimedia',
        'socios', 'usuarios', 'mensajes', 'perfil',
    ];

    // ── Estado de la interfaz (persiste entre re-renders del mismo componente)
    public string $seccionActiva = 'inicio';
    public bool   $sidebarAbierto = true;

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PÚBLICOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Cambia la sección activa del dashboard.
     * Valida contra la lista blanca para prevenir manipulación de estado.
     */
    public function cambiarSeccion(string $seccion): void
    {
        if (! in_array($seccion, self::SECCIONES_VALIDAS, strict: true)) {
            return;
        }

        $this->seccionActiva = $seccion;
    }

    /** Alterna la visibilidad del sidebar (colapsado ↔ expandido) */
    public function toggleSidebar(): void
    {
        $this->sidebarAbierto = ! $this->sidebarAbierto;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.dashboard', [
            // Cargar roles en la misma consulta para evitar N+1 en la vista
            'usuario' => auth()->user()->load('roles'),
        ]);
    }
}
