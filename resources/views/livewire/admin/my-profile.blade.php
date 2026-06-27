{{--
    Vista: "Mi Perfil" del Panel de Administración.
    Componente: App\Livewire\Admin\MyProfile

    SEGURIDAD:
     - Toda salida usa {{ }} → escape XSS automático de Blade.
     - $redesSociales se filtra aquí mismo contra esquema http(s) antes de
       construir cualquier href — defensa en profundidad (AdminRoleService
       ya las expone tal cual vienen de la BD, sin sanear el esquema).
     - La foto de perfil proviene únicamente de User::profilePhotoUrl(),
       nunca de una ruta enviada por el cliente.

    RESPONSIVE: una sola columna en cualquier viewport — esta es una ficha
    personal, no un panel multi-columna; max-w-3xl evita líneas de texto
    demasiado largas en escritorio sin desperdiciar espacio en móvil.
--}}
<div class="max-w-3xl mx-auto space-y-6">

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO CON ACCIONES (marcadores inactivos a propósito)         --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900">Mi Perfil</h1>

        <div class="flex items-center gap-2">
            <button
                type="button"
                disabled
                aria-disabled="true"
                title="No disponible por el momento"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-gray-200 text-gray-300 rounded-lg opacity-50 cursor-not-allowed pointer-events-none"
            >
                <x-admin-icon name="pencil-square" class="w-4 h-4" />
                Editar perfil
            </button>
            <button
                type="button"
                disabled
                aria-disabled="true"
                title="No disponible por el momento"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-red-100 text-red-300 rounded-lg opacity-50 cursor-not-allowed pointer-events-none"
            >
                <x-admin-icon name="no-symbol" class="w-4 h-4" />
                Inhabilitar cuenta
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TARJETA: datos del perfil                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-5">
            @if($fotoUrl)
                <img
                    src="{{ $fotoUrl }}"
                    alt="Foto de {{ $nombre }}"
                    loading="lazy"
                    decoding="async"
                    class="w-20 h-20 rounded-full object-cover flex-shrink-0"
                >
            @else
                <div class="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-2xl font-semibold">{{ $iniciales }}</span>
                </div>
            @endif

            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $nombre }}</h2>
                <p class="text-sm text-blue-600 truncate">{{ $puesto }}</p>
                <p class="text-sm text-gray-500 truncate">{{ $correo }}</p>
            </div>
        </div>

        <div class="mt-5 pt-5 border-t border-gray-100">
            <button
                type="button"
                disabled
                aria-disabled="true"
                title="No disponible por el momento"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border border-gray-200 text-gray-300 rounded-lg opacity-50 cursor-not-allowed pointer-events-none"
            >
                <x-admin-icon name="lock-closed" class="w-4 h-4" />
                Cambiar contraseña
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TARJETA: visibilidad pública (interruptor FUNCIONAL)               --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm font-medium text-gray-900">
                Mostrar mi perfil en Quiénes Somos
            </p>

            <button
                type="button"
                role="switch"
                aria-checked="{{ $mostrarEnQuienesSomos ? 'true' : 'false' }}"
                wire:click="alternarVisibilidadPublica"
                wire:loading.attr="disabled"
                wire:target="alternarVisibilidadPublica"
                class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 {{ $mostrarEnQuienesSomos ? 'bg-blue-600' : 'bg-gray-300' }}"
                aria-label="Mostrar mi perfil en Quiénes Somos"
            >
                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200 {{ $mostrarEnQuienesSomos ? 'translate-x-5' : 'translate-x-0' }}"></span>
            </button>
        </div>

        <p class="text-xs text-gray-500 mt-3 leading-relaxed">
            Si activas esta opción, tu foto, puesto, semblanza y redes sociales aparecerán
            públicamente en la sección "Nuestro Equipo" de la página "Quiénes Somos". Si la
            desactivas, tu perfil deja de mostrarse ahí. Los cambios pueden tardar hasta una
            hora en reflejarse públicamente.
        </p>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TARJETA: semblanza pública                                         --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Semblanza pública</h3>

        @if($semblanzaPublica !== '')
            <p class="text-sm text-gray-600 leading-relaxed">{{ $semblanzaPublica }}</p>
        @else
            <p class="text-sm text-gray-400 italic">Aún no has agregado una semblanza pública.</p>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- TARJETA: redes sociales                                            --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Enlaces de redes sociales</h3>

        @php
            $etiquetasRedes = [
                'website'   => 'Sitio web',
                'instagram' => 'Instagram',
                'facebook'  => 'Facebook',
                'twitter'   => 'Twitter / X',
                'linkedin'  => 'LinkedIn',
                'youtube'   => 'YouTube',
            ];

            // Solo URLs http(s) no vacías — nunca se confía en el origen del dato,
            // aunque ya venga filtrado desde el DTO/servicio (defensa en profundidad).
            $enlacesSeguros = array_filter(
                $redesSociales,
                fn ($url) => is_string($url) && $url !== '' && preg_match('#^https?://#i', $url)
            );
        @endphp

        @if(! empty($enlacesSeguros))
            <ul class="space-y-2">
                @foreach($enlacesSeguros as $plataforma => $url)
                    <li>
                        <a
                            href="{{ e($url) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors text-sm text-gray-700"
                        >
                            <span class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-600">
                                <x-social-icon name="{{ $plataforma }}" class="w-4 h-4" />
                            </span>
                            <span class="flex-1 min-w-0 truncate">{{ $etiquetasRedes[$plataforma] ?? ucfirst($plataforma) }}</span>
                            <x-admin-icon name="arrow-top-right-on-square" class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400 italic">Aún no has agregado enlaces de redes sociales.</p>
        @endif
    </div>

    {{--
        Toast de notificación: escucha el evento 'notificacion' despachado desde el
        componente Livewire. Alpine.js lo muestra 3.5 s y luego lo oculta automáticamente.
    --}}
    <div
        x-data="{ mostrar: false, tipo: '', mensaje: '' }"
        x-on:notificacion.window="
            tipo    = $event.detail.tipo;
            mensaje = $event.detail.mensaje;
            mostrar = true;
            setTimeout(() => mostrar = false, 3500);
        "
        x-show="mostrar"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        :class="tipo === 'exito' ? 'bg-green-600' : 'bg-red-600'"
        class="fixed bottom-4 right-4 z-50 px-5 py-3 rounded-lg shadow-lg text-sm font-medium text-white min-w-max"
        style="display: none;"
        role="alert"
        aria-live="polite"
    >
        <span x-text="mensaje"></span>
    </div>

</div>
