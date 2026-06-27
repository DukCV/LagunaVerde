{{--
    livewire/events/event-detail/event-detail-page.blade.php
    ─────────────────────────────────────────────────────────────────────
    Recibe: $event (EventDetailDto) — accedido como propiedades de objeto

    JERARQUÍA TÍTULO → PORTADA:
    • El <h1> es el primer elemento de contenido de la sección (debajo
      solo del botón "volver" y la categoría, que son navegación, no
      contenido del evento) — la portada ya NO va a todo el ancho detrás
      de la cabecera fija, sino contenida en una tarjeta justo debajo del
      título.
    • $event->coverUrl/coverAlt (portada) se muestra sin degradado/overlay
      — ver EventDetailDto::resolverPortada(). Incluye un visor de
      pantalla completa 100% Alpine (sin round-trip a Livewire).
    • $event->mediaItems (galería) NUNCA incluye la portada — se renderiza
      al final del cuerpo, después de la descripción y el mapa.
    • Esta separación vive en el DTO, no aquí: la vista solo decide DÓNDE
      pintar cada bloque, nunca CUÁL archivo es la portada.

    COLABORADORES:
    • $event->collaborators (array plano, ver EventDetailDto::resolverColaboradores())
      se muestra en un acordeón 100% Alpine — sin round-trip a Livewire para
      abrir/cerrar. Solo se renderiza si el array no está vacío.

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida de texto plano → escaping XSS automático.
    • {!! $event->content !!} SOLO para el campo 'content', que pasa por
      sanitizeHtml() en EventDetailDto (strip_tags + regex de atributos).
    • e() en atributos de imagen (src).
    • Los sub-componentes reciben UUID del DTO — nunca el ID entero.
    • Campos opcionales (endTime, content) renderizados condicionalmente.
    ─────────────────────────────────────────────────────────────────────
--}}

<div class="min-h-screen bg-gray-50 pb-16">

    <div class="container mx-auto px-4 pt-8">

        {{-- ── Botón volver ────────────────────────────────────────────── --}}
        <a
            href="{{ route('events') }}"
            class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700
                   mb-6 group transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="w-5 h-5 group-hover:-translate-x-1 transition-transform"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                 aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <span>Volver a Eventos</span>
        </a>

        {{-- ── Categoría (debajo del botón, en su propia línea) ───────── --}}
        @if ($event->categoryName)
            <div class="mb-4">
                <span class="inline-block px-4 py-2 bg-blue-600 text-white rounded-full text-sm font-medium">
                    {{ $event->categoryName }}
                </span>
            </div>
        @endif

        {{-- ── Título — primer elemento de contenido, antes de la portada ── --}}
        <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-6 leading-tight">
            {{ $event->title }}
        </h1>

        {{--
            ── Portada (hero) — ahora DEBAJO del título, en una tarjeta
            contenida (ya no a todo el ancho ni detrás de la cabecera fija)
            y sin degradado/overlay: la imagen se ve a su color real.

            aspect-video en vez de alturas fijas por breakpoint: el
            navegador reserva el espacio antes de descargar la imagen →
            cero CLS (Cumulative Layout Shift) en cualquier viewport, con
            una sola clase responsiva en lugar de tres.

            loading="eager" + fetchpriority="high" a propósito: sigue
            siendo la imagen más prominente y más arriba en la página
            (candidata a LCP) — diferir su carga con "lazy" empeoraría el
            rendimiento percibido, al revés de la galería del final, que
            sí carga en diferido.

            x-data vive solo en este contenedor (visor 100% Alpine, sin
            round-trip a Livewire para abrir/cerrar) — mismo patrón que
            event-carousel.blade.php, sin acoplarse a su estado interno.
        --}}
        @if ($event->coverUrl)
            <div
                x-data="{
                    modalAbierto: false,
                    abrirModal() {
                        this.modalAbierto = true;
                        document.body.classList.add('overflow-hidden');
                    },
                    cerrarModal() {
                        this.modalAbierto = false;
                        document.body.classList.remove('overflow-hidden');
                    },
                }"
                @keydown.escape.window="cerrarModal()"
                class="relative w-full aspect-video rounded-2xl overflow-hidden shadow-lg bg-gray-100 mb-8"
            >
                <img
                    src="{{ $event->coverUrl }}"
                    alt="{{ $event->coverAlt }}"
                    class="absolute inset-0 w-full h-full object-cover"
                    loading="eager"
                    fetchpriority="high"
                >

                {{-- ── Botón "Ver en pantalla completa" ────────────────── --}}
                <button
                    @click="abrirModal()"
                    type="button"
                    class="absolute top-4 right-4 z-10 w-10 h-10 bg-black/50 hover:bg-black/70
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

                {{--
                    ── Visor de pantalla completa ──────────────────────────
                    x-teleport mueve el modal al final de <body> en tiempo
                    de ejecución: queda fuera del overflow-hidden/rounded-2xl
                    de la portada y de cualquier stacking context que pudiera
                    limitarlo. Reutiliza la MISMA URL ya resuelta por el DTO
                    (sin pedir una "versión HD" aparte) → cero peticiones
                    HTTP adicionales, clave en el entorno de Hostinger.
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
                        aria-label="{{ $event->coverAlt }}"
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

                        {{-- Misma URL ya resuelta por el DTO — sin variante "HD" aparte --}}
                        <img
                            src="{{ $event->coverUrl }}"
                            alt="{{ $event->coverAlt }}"
                            class="max-w-full max-h-[90vh] w-auto h-auto object-contain rounded-lg"
                        />
                    </div>
                </template>
            </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════
             LAYOUT DE DOS COLUMNAS
        ════════════════════════════════════════════════════════════ --}}
        <div class="grid lg:grid-cols-3 gap-8">

            {{-- ── COLUMNA IZQUIERDA — contenido principal ─────────── --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- ── Información general del evento ──────────────── --}}
                <div class="bg-white rounded-2xl p-6 shadow-lg">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">
                        Información del Evento
                    </h2>

                    <div class="grid md:grid-cols-2 gap-6">

                        {{-- Fecha --}}
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center
                                        justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="1.75" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8"  y1="2" x2="8"  y2="6"/>
                                    <line x1="3"  y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Fecha</p>
                                <time datetime="{{ $event->startDateIso }}"
                                      class="text-gray-900 font-medium capitalize">
                                    {{ $event->startDate }}
                                </time>
                            </div>
                        </div>

                        {{-- Horario --}}
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center
                                        justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="1.75" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm mb-1">Horario</p>
                                <p class="text-gray-900 font-medium">
                                    {{ $event->startTime }}
                                    {{-- endTime es opcional — solo se muestra si está definida --}}
                                    @if ($event->endTime)
                                        <span aria-hidden="true"> – </span>{{ $event->endTime }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Ubicación --}}
                        @if ($event->location)
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center
                                            justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="1.75" aria-hidden="true">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-sm mb-1">Punto de Encuentro</p>
                                    <p class="text-gray-900 font-medium">{{ $event->location }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Capacidad (solo si el evento tiene límite) --}}
                        @if ($event->requiresRegistration)
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center
                                            justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M23 21v-2a4 4 0 00-3-3.87"/>
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M16 3.13a4 4 0 010 7.75"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <p class="text-gray-500 text-sm mb-1">Capacidad</p>
                                    <p class="text-gray-900 font-medium mb-2">
                                        {{ $event->registered }} / {{ $event->capacityTotal }} inscritos
                                    </p>
                                    {{-- Barra de ocupación --}}
                                    <div class="w-full bg-gray-200 rounded-full h-2"
                                         role="progressbar"
                                         aria-valuenow="{{ $event->occupancyPct }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        <div class="h-2 rounded-full transition-all
                                                    {{ $event->occupancyPct >= 90
                                                        ? 'bg-red-500'
                                                        : ($event->occupancyPct >= 70
                                                            ? 'bg-amber-500'
                                                            : 'bg-blue-600') }}"
                                             style="width: {{ $event->occupancyPct }}%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>

                {{--
                    ── Colaboradores Invitados — acordeón 100% Alpine ──────
                    x-show/x-transition corren enteramente en el cliente: no
                    hay wire:click ni round-trip al servidor para abrir o
                    cerrar la grilla (zero-latency, sin carga extra al
                    backend en Hostinger). Solo se renderiza el bloque si el
                    evento tiene al menos un colaborador — ya resuelto sin
                    N+1 por EventDetailDto::resolverColaboradores() a partir
                    del eager loading anidado de EventDetailRepository.

                    Colapsado por defecto: como los logos usan loading="lazy"
                    dentro de un contenedor con x-show (display:none), el
                    navegador no los descarga hasta que el visitante abre el
                    acordeón — ahorro de ancho de banda quien nunca lo abre.
                --}}
                @if (! empty($event->collaborators))
                    <div x-data="{ colaboradoresAbierto: false }" class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <button
                            type="button"
                            @click="colaboradoresAbierto = !colaboradoresAbierto"
                            class="w-full flex items-center justify-between gap-4 p-6 text-left
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-inset"
                            :aria-expanded="colaboradoresAbierto.toString()"
                            aria-controls="grilla-colaboradores"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center
                                            justify-center shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                         stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-semibold text-gray-900">
                                        Colaboradores Invitados
                                    </h2>
                                    <p class="text-gray-500 text-sm mt-0.5">
                                        {{ count($event->collaborators) }}
                                        {{ count($event->collaborators) === 1 ? 'organización participante' : 'organizaciones participantes' }}
                                    </p>
                                </div>
                            </div>

                            {{-- Chevron: rota 180° al abrir, transición puramente visual con CSS --}}
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-5 h-5 text-gray-400 transition-transform duration-200 shrink-0"
                                 :class="colaboradoresAbierto ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                 aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div
                            id="grilla-colaboradores"
                            x-show="colaboradoresAbierto"
                            x-cloak
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="px-6 pb-6"
                        >
                            {{-- Grilla compacta y responsiva: 2 columnas en móvil, hasta 4 en escritorio --}}
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                @foreach ($event->collaborators as $colaborador)
                                    <x-collaborators.event-card
                                        :collaborator="$colaborador"
                                        wire:key="{{ $colaborador['key'] }}"
                                    />
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── Descripción del evento ───────────────────────── --}}
                <div class="bg-white rounded-2xl p-6 shadow-lg">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                        Acerca del Evento
                    </h2>

                    @if ($event->content)
                        {{--
                            content pasa por EventDetailDto::sanitizeHtml():
                            strip_tags con allowlist + regex para eliminar
                            atributos on* y href=javascript: → seguro para {!! !!}
                        --}}
                        <div class="prose prose-lg max-w-none text-gray-700
                                    prose-headings:text-gray-900 prose-a:text-blue-600">
                            {!! $event->content !!}
                        </div>
                    @else
                        {{-- Fallback: texto plano del campo description --}}
                        <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                            {{ $event->description }}
                        </p>
                    @endif
                </div>

                {{-- ── Mapa de ubicación (integración visual) ───────── --}}
                @if ($event->location)
                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                            Ubicación
                        </h2>

                        {{--
                            x-google-maps-embed centraliza la construcción de la URL
                            (App\Support\Maps\GoogleMapsEmbed) y los atributos de
                            seguridad del iframe (sandbox, referrerpolicy) — misma
                            vista previa reutilizada en el formulario admin.
                        --}}
                        <div class="rounded-xl overflow-hidden aspect-video bg-gray-100 mb-4">
                            <x-google-maps-embed
                                :location="$event->location"
                                class="w-full h-full border-0"
                            />
                        </div>

                        <a
                            href="{{ $event->mapSearchUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700
                                   transition-colors text-sm font-medium"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"
                                 aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span>Abrir en Google Maps</span>
                        </a>
                    </div>
                @endif

                {{--
                    ── Galería Multimedia — al final del cuerpo, después de
                    descripción y mapa. Solo contiene imágenes/videos
                    adicionales: $event->mediaItems NUNCA incluye la
                    portada (ver EventDetailDto::resolverPortada()), así
                    que esta sección no repite el hero de arriba.
                    Misma tarjeta blanca que el resto de secciones, para
                    que la galería se vea integrada y no como un bloque
                    suelto — el carrusel conserva su propio fondo oscuro
                    interno (event-carousel.blade.php) por ser un visor de
                    medios, no texto.
                --}}
                @if (! empty($event->mediaItems))
                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <h2 class="text-2xl font-semibold text-gray-900 mb-4">
                            Galería Multimedia
                        </h2>

                        <livewire:events.event-detail.event-carousel
                            :items="$event->mediaItems"
                            :eventTitle="$event->title"
                            :wire:key="'carousel-' . $event->uuid"
                        />
                    </div>
                @endif

            </div>

            {{-- ── COLUMNA DERECHA — inscripción y compartir ────────── --}}
            <div class="lg:col-span-1">
                <div class="sticky top-24 space-y-6">

                    {{-- Formulario de inscripción o badge de evento abierto --}}
                    @if ($event->requiresRegistration)
                        <livewire:events.event-detail.event-attendance
                            :eventUuid="$event->uuid"
                            :wire:key="'attendance-' . $event->uuid"
                        />
                    @else
                        {{-- Evento sin límite de aforo — entrada libre --}}
                        <div class="bg-green-50 border-2 border-green-500 rounded-2xl p-8 text-center">
                            <div class="w-16 h-16 bg-green-500 rounded-full flex items-center
                                        justify-center mx-auto mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                     stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                Evento Abierto
                            </h3>
                            <p class="text-gray-700 leading-relaxed text-sm">
                                Este evento está abierto al público y no requiere registro previo.
                                ¡Todos son bienvenidos!
                            </p>
                            <p class="mt-4 pt-4 border-t border-green-200 text-gray-500 text-xs">
                                Simplemente preséntate en el lugar y horario indicado.
                            </p>
                        </div>
                    @endif

                    {{-- Sección de compartir --}}
                    <div class="bg-white rounded-2xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            Compartir Evento
                        </h3>
                        <p class="text-gray-500 text-sm mb-4">
                            Invita a tus amigos y familiares a participar
                        </p>

                        @php
                            // URLs de compartir — título y URL codificados
                            $shareUrl   = urlencode(url()->current());
                            $shareTitle = urlencode($event->title);
                        @endphp

                        <div class="flex gap-3">
                            <a
                                href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex-1 py-3 bg-blue-600 text-white text-sm font-medium
                                       rounded-lg hover:bg-blue-700 transition-colors text-center"
                                aria-label="Compartir en Facebook"
                            >
                                Facebook
                            </a>
                            <a
                                href="https://wa.me/?text={{ $shareTitle }}%20{{ $shareUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex-1 py-3 bg-green-600 text-white text-sm font-medium
                                       rounded-lg hover:bg-green-700 transition-colors text-center"
                                aria-label="Compartir en WhatsApp"
                            >
                                WhatsApp
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
