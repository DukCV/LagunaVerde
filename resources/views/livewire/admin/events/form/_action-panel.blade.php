{{--
    Panel lateral de acciones (sticky) + modal de confirmación + modal de
    campos faltantes. Mismo patrón que news-form.blade.php.
--}}
<div class="lg:sticky lg:top-24 space-y-4">
    <div class="bg-white rounded-xl p-5 shadow-lg border border-gray-200">
        <p class="text-sm font-semibold text-gray-700 mb-4">Acciones</p>

        {{-- Publicar / Actualizar --}}
        <button
            wire:click="abrirModal('publicar')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-not-allowed"
            class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium
                   rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm hover:shadow-md
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            aria-label="{{ $modo === 'editar' ? 'Actualizar evento publicado' : 'Publicar evento' }}"
        >
            <x-admin-icon name="globe-alt" class="w-4 h-4" />
            <span>{{ $modo === 'editar' ? 'Actualizar evento' : 'Publicar evento' }}</span>
        </button>

        {{-- Guardar borrador --}}
        <button
            wire:click="abrirModal('borrador')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-not-allowed"
            class="w-full flex items-center justify-center gap-2 px-5 py-2.5 mt-2.5 border border-gray-300
                   text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 active:bg-gray-100 transition-colors
                   focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
            aria-label="Guardar como borrador"
        >
            <x-admin-icon name="bookmark" class="w-4 h-4" />
            <span>Guardar borrador</span>
        </button>

        {{-- Cancelar --}}
        <button
            wire:click="abrirModal('cancelar')"
            class="w-full flex items-center justify-center gap-2 px-5 py-2.5 mt-2 text-red-600 text-sm font-medium
                   rounded-lg hover:bg-red-50 transition-colors
                   focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2"
            aria-label="Cancelar y volver a la lista"
        >
            <x-admin-icon name="x-circle" class="w-4 h-4" />
            <span>Cancelar</span>
        </button>

        <div class="pt-4 mt-2 border-t border-gray-100 space-y-2 text-xs text-gray-500">
            <div class="flex items-start gap-1.5">
                <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                <p>Los campos marcados con <span class="text-red-500">*</span> son obligatorios para publicar</p>
            </div>
        </div>
    </div>

    {{-- Indicador de carga --}}
    <div
        wire:loading.delay
        wire:target="publicar,guardarBorrador,coverImage,newSliderUploads"
        class="flex items-center gap-2 justify-center text-xs text-gray-500 bg-white rounded-lg border border-gray-200 py-2 px-3"
    >
        <svg class="animate-spin w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>Procesando...</span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- MODAL DE CONFIRMACIÓN                                              --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
@if ($modalConfirmacion)
    <div
        wire:key="modal-confirmacion-evento"
        wire:click.self="cerrarModal"
        wire:keydown.escape.window="cerrarModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
        aria-hidden="false"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-evento-titulo"
            class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
        >
            <div class="h-1.5 w-full
                @if($modalConfirmacion === 'publicar') bg-blue-500
                @elseif($modalConfirmacion === 'borrador') bg-yellow-500
                @else bg-red-500 @endif">
            </div>

            <div class="p-6 sm:p-7">
                <div class="flex justify-center mb-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center
                        @if($modalConfirmacion === 'publicar') bg-blue-100
                        @elseif($modalConfirmacion === 'borrador') bg-yellow-100
                        @else bg-red-100 @endif">
                        @if ($modalConfirmacion === 'publicar')
                            <x-admin-icon name="globe-alt" class="w-6 h-6 text-blue-600" />
                        @elseif ($modalConfirmacion === 'borrador')
                            <x-admin-icon name="bookmark" class="w-6 h-6 text-yellow-600" />
                        @else
                            <x-admin-icon name="x-circle" class="w-6 h-6 text-red-600" />
                        @endif
                    </div>
                </div>

                <h2 id="modal-evento-titulo" class="text-base font-semibold text-gray-900 text-center mb-2">
                    @if ($modalConfirmacion === 'publicar')
                        {{ $modo === 'editar' ? '¿Actualizar evento?' : '¿Publicar evento?' }}
                    @elseif ($modalConfirmacion === 'borrador')
                        ¿Guardar como borrador?
                    @else
                        ¿Cancelar sin guardar?
                    @endif
                </h2>

                <p class="text-sm text-gray-500 text-center leading-relaxed mb-6">
                    @if ($modalConfirmacion === 'publicar')
                        {{ $modo === 'editar'
                            ? 'Los cambios se publicarán y serán visibles inmediatamente para todos los usuarios.'
                            : 'El evento se publicará y será visible para todos los usuarios del sitio.' }}
                    @elseif ($modalConfirmacion === 'borrador')
                        El evento se guardará como borrador. Podrás publicarlo más tarde.
                    @else
                        Se descartarán todos los cambios no guardados. ¿Estás seguro?
                    @endif
                </p>

                <div class="flex flex-col-reverse gap-2.5 sm:flex-row">
                    <button
                        wire:click="cerrarModal"
                        autofocus
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        wire:target="publicar,guardarBorrador,cancelar"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300
                               rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                    >
                        Volver
                    </button>

                    <button
                        @if ($modalConfirmacion === 'publicar')
                            wire:click="publicar"
                        @elseif ($modalConfirmacion === 'borrador')
                            wire:click="guardarBorrador"
                        @else
                            wire:click="cancelar"
                        @endif
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        wire:target="publicar,guardarBorrador,cancelar"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors
                               focus:outline-none focus:ring-2 focus:ring-offset-2
                               @if($modalConfirmacion === 'publicar') bg-blue-600 hover:bg-blue-700 focus:ring-blue-500
                               @elseif($modalConfirmacion === 'borrador') bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-400
                               @else bg-red-600 hover:bg-red-700 focus:ring-red-500 @endif"
                    >
                        <span wire:loading.remove wire:target="publicar,guardarBorrador,cancelar">
                            @if ($modalConfirmacion === 'publicar')
                                {{ $modo === 'editar' ? 'Actualizar' : 'Publicar' }}
                            @elseif ($modalConfirmacion === 'borrador')
                                Guardar
                            @else
                                Salir
                            @endif
                        </span>
                        <span wire:loading wire:target="publicar,guardarBorrador,cancelar" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- MODAL DE CAMPOS FALTANTES PARA PUBLICAR                            --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
{{--
    corregirFechaPublicacion() + corregirFechasEvento() +
    corregirFechasInscripcion() + cerrarModalCamposFaltantes() encadenados:
    Livewire ejecuta los cuatro en UNA sola petición/re-render (no varios
    round-trips) — ver EventForm::corregirFechaPublicacion()/
    corregirFechasEvento()/corregirFechasInscripcion(). Las 3 vías de
    cierre (botón, fondo, Escape) quedan consistentes: cualquiera de ellas
    aplica la misma corrección dirigida.
--}}
@if ($mostrarModalCamposFaltantes)
    <div
        wire:key="modal-campos-faltantes-evento"
        wire:click.self="corregirFechaPublicacion(); corregirFechasEvento(); corregirFechasInscripcion(); cerrarModalCamposFaltantes()"
        wire:keydown.escape.window="corregirFechaPublicacion(); corregirFechasEvento(); corregirFechasInscripcion(); cerrarModalCamposFaltantes()"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
        aria-hidden="false"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-faltantes-evento-titulo"
            aria-describedby="modal-faltantes-evento-desc"
            class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
        >
            <div class="h-1.5 w-full bg-amber-500"></div>

            <div class="p-6 sm:p-7">
                <div class="flex justify-center mb-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-100">
                        <x-admin-icon name="exclamation-triangle" class="w-6 h-6 text-amber-600" />
                    </div>
                </div>

                <h2 id="modal-faltantes-evento-titulo" class="text-base font-semibold text-gray-900 text-center mb-2">
                    No se puede publicar todavía
                </h2>

                <p id="modal-faltantes-evento-desc" class="text-sm text-gray-500 text-center leading-relaxed mb-4">
                    Completa los siguientes campos obligatorios antes de publicar el evento:
                </p>

                {{--
                    SEGURIDAD XSS: $camposFaltantes proviene exclusivamente de
                    los mensajes de validación definidos en los Form objects
                    (nunca de input arbitrario del cliente) y se imprime con {{ }}.
                --}}
                <ul class="mb-6 space-y-2 text-sm text-gray-700">
                    @foreach ($camposFaltantes as $mensaje)
                        <li class="flex items-start gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                            <x-admin-icon name="x-circle" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-px" />
                            <span>{{ $mensaje }}</span>
                        </li>
                    @endforeach
                </ul>

                <button
                    wire:click="corregirFechaPublicacion(); corregirFechasEvento(); corregirFechasInscripcion(); cerrarModalCamposFaltantes()"
                    autofocus
                    class="w-full px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg
                           hover:bg-amber-700 active:bg-amber-800 transition-colors
                           focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2"
                    aria-label="Cerrar modal de campos faltantes"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
@endif
