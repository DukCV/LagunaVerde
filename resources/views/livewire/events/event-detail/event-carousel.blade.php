{{--
    livewire/events/event-detail/event-carousel.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe: $items (array de arrays planos), $eventTitle (string)

    VISOR DE PANTALLA COMPLETA:
    • modalAbierto/modalItem viven en el MISMO x-data raíz del carrusel
      (sin un x-data anidado nuevo) — un único árbol de estado Alpine,
      cero roundtrip a Livewire para abrir/cerrar.
    • El <template x-teleport="body"> se escribe UNA sola vez, fuera del
      x-for de diapositivas, y se mueve al final de <body> en tiempo de
      ejecución: evita que el overflow-hidden/aspect-video del carrusel
      recorte el modal y elimina cualquier problema de stacking context.
    • modalItem es una COPIA del ítem activo en el momento del clic, no
      una referencia viva a currentIndex — el carrusel queda detrás del
      overlay (z-[100], pantalla completa) y no es interactuable mientras
      el modal está abierto, así que no hace falta mantenerlos sincronizados.
    • document.body.classList.add/remove('overflow-hidden') bloquea el
      scroll de fondo mientras el modal está abierto, sin depender de
      ninguna librería extra (cero peso adicional, clave en Hostinger).

    SEGURIDAD:
    • Las URLs de imágenes/videos vienen del DTO (sanitizadas).
    • @js() codifica el array PHP de forma segura para Alpine.
    • :src/:alt usan binding de Alpine (atributo vía DOM API, no
      innerHTML) → sin riesgo de XSS aunque el texto contenga HTML.
    • Toda la navegación y el visor son client-side (Alpine.js) — sin
      roundtrips ni peticiones nuevas al servidor (el modal reutiliza la
      misma URL ya resuelta por el DTO, no pide una "versión HD" aparte).
    • Solo se renderiza si $items no está vacío (verificado en el padre).

    REPRODUCCIÓN DE VIDEO (sin fugas en segundo plano):
    • mediaCarousel() (resources/js/media/media-carousel.js) pausa y
      reinicia cualquier <video> del carrusel ANTES de cambiar de
      diapositiva (prev/next/dots) — x-show solo oculta con
      'display:none', nunca destruye el <video>, así que sin esto seguiría
      sonando de fondo tras avanzar.
    • abrirModal() llama a pausarVideos() (heredado del spread) antes de
      mostrar el visor: pausa el video inline de la diapositiva activa para
      que no siga sonando detrás del overlay de pantalla completa.
    • cerrarModal() limpia modalItem (no solo modalAbierto): el <video> del
      visor vive dentro de un <template x-if="modalItem && ...isVideo">, así
      que es modalItem el que realmente desmonta el <video> y detiene su
      reproducción — x-show por sí solo solo lo oculta sin destruirlo.
    ─────────────────────────────────────────────────────────────────────
--}}

@if (! empty($items))
<div
    x-data="{
        ...mediaCarousel(@js($items)),

        // ── Visor de pantalla completa ──────────────────────────────
        // modalAbierto/modalItem viven en este MISMO objeto (combinado por
        // spread con mediaCarousel) — un único árbol de estado Alpine,
        // cero roundtrip a Livewire para abrir/cerrar.
        modalAbierto: false,
        modalItem:    null,
        abrirModal(item) {
            this.pausarVideos();
            this.modalItem    = item;
            this.modalAbierto = true;
            document.body.classList.add('overflow-hidden');
        },
        cerrarModal() {
            this.modalAbierto = false;
            this.modalItem    = null;
            document.body.classList.remove('overflow-hidden');
        },
    }"
    @keydown.escape.window="cerrarModal()"
    class="relative bg-gray-900 rounded-2xl overflow-hidden aspect-video group"
>
    {{-- ── Elemento activo (imagen o video) ──────────────────────────── --}}
    <template x-for="(item, index) in items" :key="index">
        <div x-show="currentIndex === index" class="absolute inset-0">

            {{-- Imagen --}}
            <template x-if="item.isImage">
                <img
                    :src="item.url"
                    :alt="item.alt || ('{{ addslashes($eventTitle) }} - imagen ' + (index + 1))"
                    class="w-full h-full object-cover"
                    loading="lazy"
                />
            </template>

            {{-- Video --}}
            <template x-if="item.isVideo">
                <video
                    :src="item.url"
                    controls
                    preload="metadata"
                    class="w-full h-full object-contain bg-black"
                    :aria-label="item.title || 'Video ' + (index + 1)"
                ></video>
            </template>

        </div>
    </template>

    {{--
        ── Botón "Ver en pantalla completa" ──────────────────────────
        Único botón (no uno por cada iteración del x-for): el carrusel
        solo muestra una diapositiva a la vez, así que basta con leer
        "current" (el ítem activo) al hacer clic. Sin opacity-0
        group-hover: a diferencia de prev/next (que tienen las dots como
        respaldo táctil), este botón no tiene otra forma de descubrirse
        en móvil/tablet sin hover — debe verse siempre para cumplir con
        un diseño responsivo real, no solo de escritorio.
    --}}
    <button
        @click="abrirModal(current())"
        type="button"
        class="absolute top-4 left-4 z-10 w-10 h-10 bg-black/50 hover:bg-black/70
               text-white rounded-full flex items-center justify-center transition-colors
               focus:outline-none focus:ring-2 focus:ring-white/70"
        aria-label="Ver en pantalla completa"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9M20.25 20.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
        </svg>
    </button>

    {{-- ── Controles (solo si hay más de un elemento) ──────────────── --}}
    <template x-if="total() > 1">
        <div>
            {{-- Botón anterior --}}
            <button
                @click="prev()"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12
                       bg-white/90 hover:bg-white rounded-full flex items-center
                       justify-center shadow-lg transition-all hover:scale-110
                       opacity-0 group-hover:opacity-100"
                aria-label="Imagen anterior"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-800"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Botón siguiente --}}
            <button
                @click="next()"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12
                       bg-white/90 hover:bg-white rounded-full flex items-center
                       justify-center shadow-lg transition-all hover:scale-110
                       opacity-0 group-hover:opacity-100"
                aria-label="Siguiente imagen"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-800"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Contador --}}
            <div class="absolute top-4 right-4 px-3 py-1.5 bg-black/60 text-white
                        text-sm rounded-full select-none"
                 aria-live="polite">
                <span x-text="currentIndex + 1"></span> / <span x-text="total()"></span>
            </div>

            {{-- Dots de navegación --}}
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2"
                 role="tablist">
                <template x-for="(item, index) in items" :key="'dot-' + index">
                    <button
                        @click="goTo(index)"
                        :class="currentIndex === index
                            ? 'bg-white w-8'
                            : 'bg-white/50 w-2.5 hover:bg-white/75'"
                        class="h-2.5 rounded-full transition-all duration-200"
                        role="tab"
                        :aria-selected="currentIndex === index"
                        :aria-label="'Ir a imagen ' + (index + 1)"
                    ></button>
                </template>
            </div>
        </div>
    </template>

    {{--
        ── Visor de pantalla completa (escrito UNA sola vez) ─────────────
        x-teleport mueve este <template> al final de <body> en runtime:
        el modal queda fuera del overflow-hidden/aspect-video del
        carrusel y de cualquier contexto de apilamiento (stacking
        context) que pudiera limitarlo — sin esto, position:fixed seguiría
        funcionando visualmente en la mayoría de los casos, pero quedaría
        atado a la salud futura del CSS del carrusel; teleport lo
        independiza por completo. No requiere ningún plugin: x-teleport
        es parte del núcleo de Alpine.js desde la v3.
    --}}
    <template x-teleport="body">
        <div
            x-show="modalAbierto"
            x-cloak
            x-transition.opacity.duration.150ms
            @click.self="cerrarModal()"
            class="fixed inset-0 z-100 flex items-center justify-center p-4 sm:p-6 bg-black/90"
            role="dialog"
            aria-modal="true"
            :aria-label="(modalItem && (modalItem.title || modalItem.alt)) || 'Visor de medio en pantalla completa'"
        >
            {{-- Botón cerrar — también accesible con Escape (@keydown.escape.window arriba) --}}
            <button
                @click="cerrarModal()"
                type="button"
                class="absolute top-4 right-4 sm:top-6 sm:right-6 z-10 w-11 h-11 flex items-center
                       justify-center bg-white/10 hover:bg-white/20 text-white rounded-full
                       transition-colors focus:outline-none focus:ring-2 focus:ring-white/70"
                aria-label="Cerrar visor"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            {{--
                Contenido: misma URL ya cargada por el DTO — el modal NO
                pide una "versión en alta resolución" distinta al
                servidor, porque este proyecto no genera variantes por
                tamaño; reutilizar la única URL existente evita una
                petición HTTP adicional, clave en el entorno de Hostinger.
            --}}
            <template x-if="modalItem && modalItem.isImage">
                <img
                    :src="modalItem.url"
                    :alt="modalItem.alt || ''"
                    class="max-w-full max-h-[90vh] w-auto h-auto object-contain rounded-lg"
                />
            </template>

            <template x-if="modalItem && modalItem.isVideo">
                <video
                    :src="modalItem.url"
                    controls
                    preload="metadata"
                    class="max-w-full max-h-[90vh] w-auto h-auto object-contain rounded-lg bg-black"
                    :aria-label="modalItem.title || 'Video en pantalla completa'"
                ></video>
            </template>
        </div>
    </template>

</div>
@endif
