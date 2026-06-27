{{--
    Partial: Badge de estado de noticia.

    Variables recibidas:
      $status : string — valor interno ('published' | 'draft' | 'archived')
      $label  : string — etiqueta ya sanitizada en el DTO (nunca raw del modelo)

    SEGURIDAD: $label proviene del DTO readonly (sanitizado con strip_tags).
    Se renderiza con {{ }} para doble escape por si acaso.
--}}
@php
    $clases = match ($status) {
        'published' => 'bg-green-100 text-green-700 border-green-200',
        'scheduled' => 'bg-blue-100 text-blue-700 border-blue-200',
        'draft'     => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'archived'  => 'bg-red-100 text-red-700 border-red-200',
        'disabled'  => 'bg-orange-100 text-orange-700 border-orange-200',
        default     => 'bg-gray-100 text-gray-700 border-gray-200',
    };
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $clases }}">
    {{ $label }}
</span>
