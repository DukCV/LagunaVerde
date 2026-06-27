{{--
    Partial: Badge de rol de usuario.

    Variables recibidas:
      $roleKey   : string — clave interna ('administrador' | 'colaborador' | 'normal' | 'sin-rol')
      $roleLabel : string — etiqueta ya sanitizada en el DTO (nunca raw del modelo)

    SEGURIDAD: $roleLabel proviene del DTO readonly (sanitizado con strip_tags).
    Se renderiza con {{ }} para doble escape por si acaso.
--}}
@php
    $clases = match ($roleKey) {
        'administrador' => 'bg-blue-50 text-blue-700 border-blue-200',
        'colaborador'   => 'bg-purple-50 text-purple-700 border-purple-200',
        'normal'        => 'bg-gray-100 text-gray-600 border-gray-300',
        default         => 'bg-gray-100 text-gray-500 border-gray-200',
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $clases }}">
    {{ $roleLabel }}
</span>
