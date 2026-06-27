{{--
    Partial: Badge de estado de cuenta (activo / inactivo).

    Variables recibidas:
      $active      : bool
      $statusLabel : string — etiqueta ya calculada en el DTO ('Activo' | 'Inactivo')

    SEGURIDAD: $statusLabel proviene del DTO readonly. Se renderiza con {{ }}.
--}}
@php
    $clases = $active
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : 'bg-red-50 text-red-700 border-red-200';
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $clases }}">
    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
    {{ $statusLabel }}
</span>
