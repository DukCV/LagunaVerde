{{--
    Partial: Badge de estado de evento.

    Variables recibidas:
      $status : string — valor interno ('published' | 'draft' | 'cancelled' | 'closed')
      $label  : string — etiqueta ya sanitizada en el DTO (nunca raw del modelo)

    SEGURIDAD: $label proviene del DTO readonly (sanitizado con strip_tags).
    Se renderiza con {{ }} para doble escape por si acaso.
--}}
@php
    $clases = match ($status) {
        'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'draft'     => 'bg-amber-50 text-amber-700 border-amber-200',
        'cancelled' => 'bg-red-50 text-red-700 border-red-200',
        'closed'    => 'bg-gray-100 text-gray-600 border-gray-300',
        default     => 'bg-gray-100 text-gray-700 border-gray-200',
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $clases }}">
    {{ $label }}
</span>
