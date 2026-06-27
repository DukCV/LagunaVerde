{{--
    Partial: Botones de acción para una tarjeta de usuario.

    Variables recibidas:
      $usuario : App\DTOs\Admin\AdminUserItemDto

    "Inhabilitar"/"Activar" abre el modal de confirmación con contraseña
    (ver users-management.blade.php y UsersManagement::confirmarToggleEstado()).
    "Administrar rol" abre el sub-componente UserRoleManager (ver
    UsersManagement::confirmarGestionRol()) — deshabilitado tanto para la
    propia cuenta como para cualquier cuenta INHABILITADA. "Borrar
    definitivamente" sigue deshabilitado a propósito — sin lógica de
    mutación todavía.

    SEGURIDAD: $usuario->id/name/active provienen del DTO readonly, nunca de
    un valor enviado por el cliente. El propio backend (UsersManagement y
    UserRoleManager) vuelve a rechazar ambos casos con una lectura fresca de
    la BD — este deshabilitado visual es solo la primera capa, no la única.
--}}
@php
    $clasesBase = 'flex items-center justify-center gap-1 px-2 py-2 text-xs font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1';
    $esMiPropiaCuenta = $usuario->id === auth()->id();

    // Motivo por el que "Administrar rol" está deshabilitado, si aplica —
    // un solo punto de verdad para el título/aria-label del botón inactivo.
    $razonRolDeshabilitado = match (true) {
        $esMiPropiaCuenta   => 'No puedes administrar tu propio rol',
        ! $usuario->active  => 'No puedes administrar el rol de una cuenta inhabilitada',
        default             => null,
    };
@endphp

{{-- Inhabilitar / Activar — única acción funcional --}}
@if($esMiPropiaCuenta)
    <button
        type="button"
        disabled
        aria-disabled="true"
        title="No puedes cambiar el estado de tu propia cuenta"
        aria-label="No puedes cambiar el estado de tu propia cuenta"
        class="{{ $clasesBase }} border border-gray-200 text-gray-300 cursor-not-allowed opacity-50"
    >
        <x-admin-icon name="no-symbol" class="w-3.5 h-3.5 flex-shrink-0" />
        <span>{{ $usuario->active ? 'Inhabilitar' : 'Activar' }}</span>
    </button>
@else
    <button
        type="button"
        wire:click="confirmarToggleEstado({{ $usuario->id }})"
        class="{{ $clasesBase }} {{ $usuario->active
            ? 'border border-amber-300 text-amber-600 hover:bg-amber-50 active:bg-amber-100 focus:ring-amber-400'
            : 'border border-emerald-300 text-emerald-600 hover:bg-emerald-50 active:bg-emerald-100 focus:ring-emerald-400' }}"
        aria-label="{{ $usuario->active ? 'Inhabilitar' : 'Activar' }} a {{ $usuario->name }}"
    >
        <x-admin-icon name="{{ $usuario->active ? 'no-symbol' : 'check-circle' }}" class="w-3.5 h-3.5 flex-shrink-0" />
        <span>{{ $usuario->active ? 'Inhabilitar' : 'Activar' }}</span>
    </button>
@endif

{{-- Administrar rol --}}
@if($razonRolDeshabilitado !== null)
    <button
        type="button"
        disabled
        aria-disabled="true"
        title="{{ $razonRolDeshabilitado }}"
        aria-label="{{ $razonRolDeshabilitado }}"
        class="{{ $clasesBase }} border border-gray-200 text-gray-300 cursor-not-allowed opacity-50"
    >
        <x-admin-icon name="arrow-path" class="w-3.5 h-3.5 flex-shrink-0" />
        <span>Administrar rol</span>
    </button>
@else
    <button
        type="button"
        wire:click="confirmarGestionRol({{ $usuario->id }})"
        class="{{ $clasesBase }} border border-blue-300 text-blue-600 hover:bg-blue-50 active:bg-blue-100 focus:ring-blue-400"
        aria-label="Administrar rol de {{ $usuario->name }}"
    >
        <x-admin-icon name="arrow-path" class="w-3.5 h-3.5 flex-shrink-0" />
        <span>Administrar rol</span>
    </button>
@endif

{{-- Borrar definitivamente --}}
<button
    type="button"
    disabled
    aria-disabled="true"
    title="No disponible por el momento"
    aria-label="Borrar definitivamente a {{ $usuario->name }}"
    class="{{ $clasesBase }} border border-red-100 text-red-300 cursor-not-allowed opacity-50"
>
    <x-admin-icon name="trash" class="w-3.5 h-3.5 flex-shrink-0" />
    <span>Borrar</span>
</button>
