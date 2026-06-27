{{--
    Partial: Avatar en miniatura para listas/tarjetas de selección de usuario
    (selector "Vincular usuario" del formulario de socios colaboradores).

    Variables recibidas:
      $avatarUrl : string|null
      $initials  : string  — se muestra si no hay foto de perfil
      $name      : string  — usado solo en el alt de la imagen
      $size      : string  — clases Tailwind de tamaño (ej. 'w-9 h-9')

    SEGURIDAD: $avatarUrl proviene únicamente de User::profilePhotoUrl();
    $name/$initials se renderizan con {{ }} → escape XSS automático.
--}}
@if($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        alt="Foto de {{ $name }}"
        loading="lazy"
        decoding="async"
        class="{{ $size }} rounded-full object-cover flex-shrink-0"
    >
@else
    <div class="{{ $size }} rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
        <span class="text-white text-xs font-semibold">{{ $initials }}</span>
    </div>
@endif
