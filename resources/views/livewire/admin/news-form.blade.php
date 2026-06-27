{{--
    Vista: Formulario de Creación/Edición de Noticias
    Componente: App\Livewire\Admin\NewsForm

    MODOS:
     - 'crear': formulario vacío, botón "Publicar noticia".
     - 'editar': formulario pre-rellenado, botón "Actualizar noticia".

    EDITOR DE CONTENIDO:
     - Trix, vía <x-rich-text::trix> (paquete tonysm/rich-text-laravel).
     - wire:ignore previene que Livewire sobreescriba el DOM del editor en cada re-render.
     - El contenido se sincroniza con la propiedad $contenido vía wire:model
       ($wire.entangle), gestionado internamente por el propio componente Trix.

    SEGURIDAD:
     - Toda variable de usuario renderizada con {{ }} → escape XSS automático de Blade.
     - {!! !!} solo donde el HTML fue previamente sanitizado (ninguno en esta vista).
     - wire:click solo invoca métodos explícitos del componente (sin eval).
     - Los IDs de media pasados a wire:click son enteros validados en el servidor.

    ACCESIBILIDAD:
     - Todos los inputs tienen <label> asociado.
     - Botones de acción tienen aria-label descriptivo.
     - role="dialog" + aria-modal en el modal de confirmación.
     - wire:loading.attr="disabled" en botones durante peticiones activas.
--}}
<div class="space-y-6">

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- ENCABEZADO CON NAVEGACIÓN                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3">
        <button
            wire:click="cancelar"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300"
            aria-label="Volver a la lista de noticias"
        >
            <x-admin-icon name="chevron-left" class="w-5 h-5 text-gray-600" />
        </button>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $modo === 'editar' ? 'Editar Noticia' : 'Crear Nueva Noticia' }}
            </h1>
            <p class="text-sm text-gray-500">
                {{ $modo === 'editar'
                    ? 'Modifica el contenido y actualiza la publicación'
                    : 'Completa el formulario para publicar una nueva noticia' }}
            </p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- LAYOUT PRINCIPAL: formulario izquierda + acciones derecha          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-[1fr_300px] gap-6 items-start">

        {{-- ── Columna principal ─────────────────────────────────────── --}}
        <div class="space-y-6 min-w-0">

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Categoría + Título + Autor + Fecha         │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 space-y-5">

                {{-- Categoría --}}
                <div>
                    <label for="form-categoria" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Categoría <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="form-categoria"
                            wire:model="categoriaId"
                            class="w-full appearance-none px-4 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-9
                                   @error('categoriaId') border-red-400 bg-red-50 @enderror"
                            aria-required="true"
                            aria-describedby="{{ $errors->has('categoriaId') ? 'err-categoria' : null }}"
                        >
                            <option value="">— Selecciona una categoría —</option>
                            @foreach ($categorias as $id => $nombre)
                                <option value="{{ $id }}" @selected($categoriaId == $id)>
                                    {{ $nombre }}
                                </option>
                            @endforeach
                        </select>
                        <x-admin-icon name="chevron-down"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                    </div>
                    @error('categoriaId')
                        <p id="err-categoria" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Título --}}
                <div>
                    <label for="form-titulo" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Título <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="form-titulo"
                        wire:model="titulo"
                        placeholder="Escribe un título claro y descriptivo"
                        maxlength="220"
                        autocomplete="off"
                        class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                               @error('titulo') border-red-400 bg-red-50 @enderror"
                        aria-required="true"
                    >
                    <div class="flex items-center justify-between mt-1">
                        @error('titulo')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <span></span>
                        @enderror
                        <p class="text-xs text-gray-400 ml-auto">{{ mb_strlen($titulo) }} / 220</p>
                    </div>
                </div>

                {{-- Resumen --}}
                <div>
                    <label for="form-resumen" class="block text-sm font-medium text-gray-700 mb-1.5">
                        Resumen
                        <span class="text-gray-400 font-normal ml-1">(Opcional)</span>
                    </label>
                    <textarea
                        id="form-resumen"
                        wire:model="resumen"
                        placeholder="Descripción breve que aparece en las tarjetas del listado..."
                        maxlength="800"
                        rows="3"
                        class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg resize-y
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                               @error('resumen') border-red-400 bg-red-50 @enderror"
                    ></textarea>
                    @error('resumen')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Autor (solo lectura) + Fecha de publicación --}}
                <div class="pt-4 border-t border-gray-100 grid sm:grid-cols-2 gap-5">

                    {{-- Autor --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1.5">
                            {{ $modo === 'editar' ? 'Autor original' : 'Autor de la publicación' }}
                        </p>
                        <div class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-blue-600 text-xs font-semibold">
                                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm text-gray-900 truncate">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-gray-500">Administrador</div>
                            </div>
                        </div>
                    </div>

                    {{-- Fecha de publicación --}}
                    {{--
                        REGLA DE NEGOCIO:
                         Si el artículo fue alguna vez publicado (fechaPublicacionBloqueada=true),
                         el campo queda deshabilitado: la fecha original se preserva intacta.
                         Solo se actualiza updated_at al guardar cambios posteriores.
                         Si nunca fue publicado (borrador puro), el campo es totalmente editable.
                    --}}
                    <div>
                        <label for="form-fecha" class="block text-sm font-medium text-gray-700 mb-1.5">
                            Fecha de Publicación
                            @if (! $fechaPublicacionBloqueada)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>

                        <div
                            class="relative"
                            x-data="{
                                // sincronizarFecha(): empuja el valor al servidor con
                                // $wire.set() en cuanto el navegador confirma un cambio
                                // (evento change). A diferencia de wire:model.blur, el
                                // evento change de un campo de fecha se dispara tanto al
                                // elegir una fecha en el selector nativo (sin que el campo
                                // pierda el foco) como al terminar de escribirla
                                // manualmente, garantizando sincronización instantánea en
                                // ambos flujos de interacción.
                                //
                                // Tras resolverse la petición, updatedFechaPublicacion() ya
                                // pudo haber corregido la fecha en el servidor (regla de
                                // fechas pasadas); $wire.fechaPublicacion refleja ese valor
                                // corregido y se reescribe en el input de inmediato.
                                sincronizarFecha(valor) {
                                    $wire.set('fechaPublicacion', valor).then(() => {
                                        $refs.fechaInput.value = $wire.fechaPublicacion;
                                    });
                                },
                            }"
                        >
                            <input
                                type="date"
                                id="form-fecha"
                                x-ref="fechaInput"
                                value="{{ $fechaPublicacion }}"
                                @change="sincronizarFecha($event.target.value)"
                                @if ($fechaPublicacionBloqueada)
                                    disabled
                                    readonly
                                    aria-disabled="true"
                                    aria-describedby="fecha-bloqueada-nota"
                                @else
                                    aria-required="true"
                                    aria-invalid="{{ $errors->has('fechaPublicacion') ? 'true' : 'false' }}"
                                    aria-describedby="{{ $errors->has('fechaPublicacion') ? 'err-fecha' : ($avisoFechaPasada ? 'aviso-fecha-pasada' : 'fecha-nota') }}"
                                @endif
                                class="admin-date-input w-full px-4 py-2.5 pr-9 text-sm rounded-lg transition-all
                                       @if ($fechaPublicacionBloqueada)
                                           border border-amber-200 bg-amber-50 text-amber-700 cursor-not-allowed
                                       @else
                                           border focus:outline-none focus:ring-2 focus:border-transparent
                                           @if ($avisoFechaPasada)
                                               border-amber-300 bg-amber-50 focus:ring-amber-400
                                           @else
                                               border-gray-300 focus:ring-blue-500
                                           @endif
                                           @error('fechaPublicacion') border-red-400 bg-red-50 @enderror
                                       @endif"
                            >
                            @if ($fechaPublicacionBloqueada)
                                {{-- Ícono de candado superpuesto cuando el campo está bloqueado --}}
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                    <x-admin-icon name="lock-closed" class="w-4 h-4 text-amber-500" />
                                </span>
                            @else
                                {{-- Botón de calendario: abre el selector de fecha nativo del navegador --}}
                                <button
                                    type="button"
                                    @click="$refs.fechaInput.showPicker?.()"
                                    class="absolute inset-y-0 right-1.5 flex items-center px-1.5 text-gray-400 hover:text-blue-600 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    aria-label="Abrir selector de fecha"
                                    tabindex="-1"
                                >
                                    <x-admin-icon name="calendar-days" class="w-4 h-4" />
                                </button>
                            @endif
                        </div>

                        @if ($fechaPublicacionBloqueada)
                            <p id="fecha-bloqueada-nota" class="mt-1.5 text-xs text-amber-600 flex items-start gap-1">
                                <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                                La fecha se fijó al publicar por primera vez y no puede modificarse.
                            </p>
                        @else
                            {{--
                                Aviso de auto-corrección (prioridad sobre la nota informativa):
                                role="alert" + aria-live="assertive" garantizan que los lectores
                                de pantalla anuncien el cambio de valor de inmediato.
                                SEGURIDAD XSS: $fechaPublicacion se imprime con {{ }} (escape automático).
                            --}}
                            @if ($avisoFechaPasada)
                                <p id="aviso-fecha-pasada" role="alert" aria-live="assertive"
                                   class="mt-1.5 text-xs text-amber-700 flex items-start gap-1 font-medium">
                                    <x-admin-icon name="exclamation-triangle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                                    No se permiten fechas pasadas. La fecha se ajustó automáticamente a hoy ({{ $fechaPublicacion }}).
                                </p>
                            @endif

                            @error('fechaPublicacion')
                                <p id="err-fecha" class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror

                            @if (! $avisoFechaPasada && ! $errors->has('fechaPublicacion'))
                                <p id="fecha-nota" class="mt-1 text-xs text-gray-400">
                                    @if ($estadoActual === 'scheduled')
                                        Esta noticia está programada: elige otra fecha futura para reprogramarla, o la fecha de hoy para publicarla de inmediato.
                                    @elseif ($modo === 'editar' && $estadoActual === 'draft' && $fechaPublicacion === now()->format('Y-m-d'))
                                        La fecha de hoy: al guardar como borrador podrás elegir publicar la noticia ahora.
                                    @else
                                        Una fecha futura programa la publicación automáticamente
                                    @endif
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Imagen de Portada                          │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-3">
                    Imagen de Portada <span class="text-red-500">*</span>
                </p>

                {{--
                    Vista previa unificada: portada existente O nueva imagen seleccionada.
                    Se muestra la imagen completa con object-contain para no recortar,
                    con altura máxima de 280px. Botones siempre visibles debajo.
                --}}
                @php
                    $previewUrl   = $imagenPortada
                        ? $imagenPortada->temporaryUrl()
                        : (($portadaActualUrl && ! $eliminarPortada) ? $portadaActualUrl : null);
                    $esNuevaImagen = (bool) $imagenPortada;
                @endphp

                @if ($previewUrl)
                    {{-- Contenedor de vista previa --}}
                    <div class="mb-4 rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-center" style="max-height: 280px; min-height: 140px;">
                            <img
                                src="{{ $previewUrl }}"
                                alt="{{ $esNuevaImagen ? 'Nueva portada seleccionada' : 'Portada actual' }}"
                                class="max-w-full max-h-[280px] w-auto h-auto object-contain"
                            >
                        </div>
                    </div>

                    {{-- Botones de acción siempre visibles --}}
                    <div class="flex items-center gap-2 mb-4">
                        {{-- Cambiar imagen --}}
                        <label
                            for="input-portada"
                            id="btn-cambiar-portada"
                            class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-white border border-gray-300
                                   text-sm text-gray-700 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors
                                   focus-within:ring-2 focus-within:ring-blue-500"
                            aria-label="Cambiar imagen de portada"
                        >
                            <x-admin-icon name="arrow-path" class="w-4 h-4 text-gray-500" />
                            Cambiar imagen
                        </label>

                        {{-- Quitar imagen --}}
                        @if ($esNuevaImagen)
                            <button
                                id="btn-quitar-portada-nueva"
                                wire:click="$set('imagenPortada', null)"
                                class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-red-50 border border-red-200
                                       text-sm text-red-600 rounded-lg hover:bg-red-100 transition-colors
                                       focus:outline-none focus:ring-2 focus:ring-red-400"
                                aria-label="Quitar imagen seleccionada"
                            >
                                <x-admin-icon name="x-mark" class="w-4 h-4" />
                                Quitar imagen
                            </button>
                        @else
                            <button
                                id="btn-eliminar-portada-existente"
                                wire:click="toggleEliminarPortada"
                                class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2 bg-red-50 border border-red-200
                                       text-sm text-red-600 rounded-lg hover:bg-red-100 transition-colors
                                       focus:outline-none focus:ring-2 focus:ring-red-400"
                                aria-label="Eliminar portada actual"
                            >
                                <x-admin-icon name="trash" class="w-4 h-4" />
                                Quitar imagen
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Zona de carga (visible cuando no hay ninguna imagen) --}}
                @if (! $previewUrl)
                    <label
                        for="input-portada"
                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300
                               rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all text-gray-400 hover:text-blue-500"
                    >
                        <x-admin-icon name="cloud-arrow-up" class="w-10 h-10 mb-2" />
                        <span class="text-sm">Haz clic para subir una imagen</span>
                        <span class="text-xs mt-0.5">JPG, PNG, WEBP (máx. 5 MB)</span>
                    </label>
                @endif

                {{-- Input oculto para la portada + indicador de carga --}}
                <div
                    x-data="{ subiendo: false }"
                    x-on:livewire:upload-start.window="subiendo = true"
                    x-on:livewire:upload-finish.window="subiendo = false"
                    x-on:livewire:upload-error.window="subiendo = false"
                >
                    <input
                        type="file"
                        id="input-portada"
                        wire:model="imagenPortada"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        aria-label="Subir imagen de portada"
                    >
                    <div x-show="subiendo" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Subiendo imagen...</span>
                    </div>
                </div>

                @error('imagenPortada')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Editor de Contenido (Trix)                 │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <p class="text-sm font-medium text-gray-700 mb-3">
                    Cuerpo de la Noticia <span class="text-red-500">*</span>
                </p>

                {{--
                    wire:ignore previene que Livewire toque el DOM del editor en re-renders.
                    <x-rich-text::trix wire:model="contenido"> ya gestiona, vía Alpine:
                      - la carga del HTML inicial al disparase trix-initialize,
                      - la sincronización Trix → Livewire en trix-change ($wire.entangle).
                    x-init fija el placeholder directamente en <trix-editor> (la prop
                    "placeholder" del componente no existe; solo acepta id/name/value).
                    x-on:trix-file-accept previene adjuntos de archivo: las imágenes de
                    la noticia se gestionan en el slider de medios, no inline en el cuerpo.
                --}}
                <div
                    wire:ignore
                    x-init="document.getElementById('contenido-editor').setAttribute('placeholder', 'Escribe el contenido principal de la noticia. Usa la barra de herramientas para dar formato al texto...')"
                    x-on:trix-file-accept="$event.preventDefault()"
                    class="@error('contenido') ring-1 ring-red-400 rounded-lg @enderror"
                >
                    <x-rich-text::trix
                        id="contenido-editor"
                        name="contenido"
                        wire:model="contenido"
                        :value="$contenido"
                    />
                </div>

                @error('contenido')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-400">
                    Usa la barra de herramientas para: negritas, títulos, listas, citas, código, enlaces, etc.
                </p>
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Slider de Imágenes y Videos (Opcional)     │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-medium text-gray-700">
                        Slider de Imágenes y Vídeos
                        <span class="text-gray-400 font-normal ml-1">(Opcional)</span>
                    </p>
                    <label
                        for="input-medios"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 text-sm rounded-lg
                               hover:bg-blue-100 cursor-pointer transition-colors"
                        aria-label="Agregar imágenes o vídeos al slider"
                    >
                        <x-admin-icon name="plus" class="w-4 h-4" />
                        Agregar medios
                    </label>
                </div>

                {{-- Input múltiple para nuevos medios del slider --}}
                <input
                    type="file"
                    id="input-medios"
                    wire:model="mediosNuevos"
                    accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"
                    multiple
                    class="hidden"
                    aria-label="Seleccionar imágenes o vídeos"
                >

                @error('mediosNuevos.*')
                    <p class="mb-3 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- Medios EXISTENTES (solo en modo edición) --}}
                @if (! empty($mediosExistentes))
                    <div class="mb-4">
                        <p class="text-xs text-gray-500 mb-2 uppercase tracking-wide">Archivos guardados</p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                            @foreach ($mediosExistentes as $medio)
                                <div wire:key="medio-existente-{{ $medio['id'] }}" class="relative group aspect-square">
                                    @if ($medio['tipo'] === 'imagen')
                                        <img
                                            src="{{ $medio['url'] }}"
                                            alt="{{ $medio['nombre'] }}"
                                            class="w-full h-full object-cover rounded-lg border border-gray-200"
                                            loading="lazy"
                                        >
                                    @else
                                        <div class="w-full h-full bg-gray-100 rounded-lg border border-gray-200 flex flex-col items-center justify-center gap-1">
                                            <x-admin-icon name="video-camera" class="w-6 h-6 text-gray-400" />
                                            <span class="text-xs text-gray-400 text-center px-1 line-clamp-2">{{ $medio['nombre'] }}</span>
                                        </div>
                                    @endif
                                    {{-- Botón de eliminar superpuesto --}}
                                    <button
                                        wire:click="marcarMedioParaEliminar({{ $medio['id'] }})"
                                        wire:confirm="¿Eliminar este archivo?"
                                        class="absolute top-1 right-1 w-6 h-6 bg-red-600 text-white rounded-full
                                               opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center
                                               hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400"
                                        aria-label="Eliminar {{ $medio['nombre'] }}"
                                    >
                                        <x-admin-icon name="x-mark" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Nuevos medios subidos (TemporaryUploadedFile) --}}
                @if (! empty($mediosNuevos))
                    <div>
                        <p class="text-xs text-gray-500 mb-2 uppercase tracking-wide">Nuevos archivos (sin guardar)</p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                            @foreach ($mediosNuevos as $indice => $medio)
                                <div wire:key="medio-nuevo-{{ $indice }}" class="relative group aspect-square">
                                    @if (str_starts_with($medio->getMimeType(), 'image/'))
                                        <img
                                            src="{{ $medio->temporaryUrl() }}"
                                            alt="Nuevo medio {{ $indice + 1 }}"
                                            class="w-full h-full object-cover rounded-lg border-2 border-blue-300"
                                        >
                                    @else
                                        <div class="w-full h-full bg-blue-50 rounded-lg border-2 border-blue-300 flex flex-col items-center justify-center gap-1 p-1">
                                            <x-admin-icon name="video-camera" class="w-6 h-6 text-blue-400" />
                                            <span class="text-xs text-blue-600 text-center line-clamp-2">{{ $medio->getClientOriginalName() }}</span>
                                        </div>
                                    @endif
                                    <button
                                        wire:click="quitarMedioNuevo({{ $indice }})"
                                        class="absolute top-1 right-1 w-6 h-6 bg-red-600 text-white rounded-full
                                               opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center
                                               hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400"
                                        aria-label="Quitar archivo nuevo {{ $indice + 1 }}"
                                    >
                                        <x-admin-icon name="x-mark" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Estado vacío --}}
                @if (empty($mediosExistentes) && empty($mediosNuevos))
                    <div class="border-2 border-dashed border-gray-200 rounded-lg p-8 text-center text-gray-400">
                        <x-admin-icon name="photo" class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                        <p class="text-sm">No hay medios en el slider</p>
                        <p class="text-xs mt-0.5">Haz clic en "Agregar medios" para subir imágenes o vídeos</p>
                    </div>
                @endif
            </div>

            {{-- ┌─────────────────────────────────────────────────────┐ --}}
            {{-- │  TARJETA: Documentos Descargables (Opcional)         │ --}}
            {{-- └─────────────────────────────────────────────────────┘ --}}
            <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm font-medium text-gray-700">
                        Documentos Descargables
                        <span class="text-gray-400 font-normal ml-1">(Opcional)</span>
                    </p>
                    <label
                        for="input-documentos"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 text-sm rounded-lg
                               hover:bg-blue-100 cursor-pointer transition-colors"
                        aria-label="Agregar documentos descargables"
                    >
                        <x-admin-icon name="plus" class="w-4 h-4" />
                        Agregar archivos
                    </label>
                </div>

                <input
                    type="file"
                    id="input-documentos"
                    wire:model="documentosNuevos"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                    multiple
                    class="hidden"
                    aria-label="Seleccionar documentos"
                >

                @error('documentosNuevos.*')
                    <p class="mb-3 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- Documentos EXISTENTES --}}
                @if (! empty($documentosExistentes))
                    <div class="space-y-2 mb-4">
                        @foreach ($documentosExistentes as $doc)
                            <div
                                wire:key="doc-existente-{{ $doc['id'] }}"
                                class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                <x-admin-icon name="document" class="w-5 h-5 text-gray-400 flex-shrink-0" />
                                <span class="flex-1 text-sm text-gray-800 truncate">{{ $doc['nombre'] }}</span>
                                <button
                                    wire:click="marcarDocumentoParaEliminar({{ $doc['id'] }})"
                                    wire:confirm="¿Eliminar este documento?"
                                    class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0
                                           focus:outline-none focus:ring-2 focus:ring-red-400"
                                    aria-label="Eliminar {{ $doc['nombre'] }}"
                                >
                                    <x-admin-icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Nuevos documentos (sin guardar) --}}
                @if (! empty($documentosNuevos))
                    <div class="space-y-2">
                        @foreach ($documentosNuevos as $indice => $doc)
                            <div
                                wire:key="doc-nuevo-{{ $indice }}"
                                class="flex items-center gap-3 p-3 border-2 border-blue-200 bg-blue-50 rounded-lg"
                            >
                                <x-admin-icon name="document" class="w-5 h-5 text-blue-400 flex-shrink-0" />
                                <span class="flex-1 text-sm text-blue-800 truncate">{{ $doc->getClientOriginalName() }}</span>
                                <button
                                    wire:click="quitarDocumentoNuevo({{ $indice }})"
                                    class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0
                                           focus:outline-none focus:ring-2 focus:ring-red-400"
                                    aria-label="Quitar documento nuevo"
                                >
                                    <x-admin-icon name="x-mark" class="w-4 h-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Estado vacío --}}
                @if (empty($documentosExistentes) && empty($documentosNuevos))
                    <div class="border-2 border-dashed border-gray-200 rounded-lg p-8 text-center text-gray-400">
                        <x-admin-icon name="document" class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                        <p class="text-sm">No hay documentos adjuntos</p>
                        <p class="text-xs mt-0.5">PDF, DOC, XLS, PPT (máx. 10 MB por archivo)</p>
                    </div>
                @endif
            </div>

        </div>{{-- /columna principal --}}

        {{-- ── Panel lateral de acciones (sticky) ────────────────────── --}}
        <div class="lg:sticky lg:top-24 space-y-4">
            <div class="bg-white rounded-xl p-5 shadow-lg border border-gray-200">
                <p class="text-sm font-semibold text-gray-700 mb-4">Acciones</p>

                {{-- Botón Publicar / Actualizar --}}
                <button
                    wire:click="abrirModal('publicar')"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70 cursor-not-allowed"
                    class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-medium
                           rounded-lg hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm hover:shadow-md
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    aria-label="{{ $modo === 'editar' ? 'Actualizar noticia publicada' : 'Publicar noticia' }}"
                >
                    <x-admin-icon name="check" class="w-4 h-4" />
                    <span>{{ $modo === 'editar' ? 'Actualizar noticia' : 'Publicar noticia' }}</span>
                </button>

                {{-- Botón Guardar borrador --}}
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

                {{-- Botón Cancelar --}}
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

                {{-- Notas informativas --}}
                <div class="pt-4 mt-2 border-t border-gray-100 space-y-2 text-xs text-gray-500">
                    <div class="flex items-start gap-1.5">
                        <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                        <p>Los campos marcados con <span class="text-red-500">*</span> son obligatorios</p>
                    </div>
                    <div class="flex items-start gap-1.5">
                        <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
                        <p>Una fecha futura programa la publicación automáticamente</p>
                    </div>
                </div>
            </div>

            {{-- Indicador de carga --}}
            <div
                wire:loading.delay
                wire:target="publicar,programar,guardarBorrador,imagenPortada,mediosNuevos,documentosNuevos"
                class="flex items-center gap-2 justify-center text-xs text-gray-500 bg-white rounded-lg border border-gray-200 py-2 px-3"
            >
                <svg class="animate-spin w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>Procesando...</span>
            </div>
        </div>

    </div>{{-- /grid --}}

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE CONFIRMACIÓN                                              --}}
    {{-- ACCESIBILIDAD: role="dialog", aria-modal, aria-labelledby.        --}}
    {{-- SEGURIDAD: toda variable usa {{ }} → escape XSS automático.       --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if ($modalConfirmacion)
        <div
            wire:key="modal-confirmacion-form"
            wire:click.self="cerrarModal"
            wire:keydown.escape.window="cerrarModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="false"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-form-titulo"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                {{-- Franja de color según acción --}}
                <div class="h-1.5 w-full
                    @if($modalConfirmacion === 'publicar') bg-blue-500
                    @elseif($modalConfirmacion === 'programar') bg-indigo-500
                    @elseif($modalConfirmacion === 'publicar_ahora') bg-emerald-500
                    @elseif($modalConfirmacion === 'borrador') bg-yellow-500
                    @else bg-red-500 @endif">
                </div>

                <div class="p-6 sm:p-7">
                    {{-- Ícono central --}}
                    <div class="flex justify-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center
                            @if($modalConfirmacion === 'publicar') bg-blue-100
                            @elseif($modalConfirmacion === 'programar') bg-indigo-100
                            @elseif($modalConfirmacion === 'publicar_ahora') bg-emerald-100
                            @elseif($modalConfirmacion === 'borrador') bg-yellow-100
                            @else bg-red-100 @endif">
                            @if ($modalConfirmacion === 'publicar')
                                <x-admin-icon name="check-circle" class="w-6 h-6 text-blue-600" />
                            @elseif ($modalConfirmacion === 'programar')
                                <x-admin-icon name="clock" class="w-6 h-6 text-indigo-600" />
                            @elseif ($modalConfirmacion === 'publicar_ahora')
                                <x-admin-icon name="check-circle" class="w-6 h-6 text-emerald-600" />
                            @elseif ($modalConfirmacion === 'borrador')
                                <x-admin-icon name="bookmark" class="w-6 h-6 text-yellow-600" />
                            @else
                                <x-admin-icon name="x-circle" class="w-6 h-6 text-red-600" />
                            @endif
                        </div>
                    </div>

                    {{-- Título --}}
                    <h2 id="modal-form-titulo" class="text-base font-semibold text-gray-900 text-center mb-2">
                        @if ($modalConfirmacion === 'publicar')
                            {{ $modo === 'editar' ? '¿Actualizar noticia?' : '¿Publicar noticia?' }}
                        @elseif ($modalConfirmacion === 'programar')
                            ¿Programar publicación?
                        @elseif ($modalConfirmacion === 'publicar_ahora')
                            ¿Quieres publicar la noticia ahora?
                        @elseif ($modalConfirmacion === 'borrador')
                            ¿Guardar como borrador?
                        @else
                            ¿Cancelar sin guardar?
                        @endif
                    </h2>

                    {{-- Descripción --}}
                    <p class="text-sm text-gray-500 text-center leading-relaxed mb-6">
                        @if ($modalConfirmacion === 'publicar')
                            {{ $modo === 'editar'
                                ? 'Los cambios se publicarán y serán visibles inmediatamente para todos los usuarios.'
                                : 'La noticia se publicará y será visible para todos los usuarios del sitio.' }}
                        @elseif ($modalConfirmacion === 'programar')
                            {{-- SEGURIDAD XSS: $fechaHoraProgramada se imprime con {{ }} (escape automático). --}}
                            La noticia se publicará automáticamente el <strong class="text-gray-700">{{ $fechaHoraProgramada }}</strong>. Hasta entonces quedará guardada como programada.
                        @elseif ($modalConfirmacion === 'publicar_ahora')
                            La fecha de publicación es hoy. Puedes publicarla ahora para que sea visible de inmediato, o seguir guardándola como borrador.
                        @elseif ($modalConfirmacion === 'borrador')
                            La noticia se guardará como borrador. Podrás publicarla más tarde.
                        @else
                            Se descartarán todos los cambios no guardados. ¿Estás seguro?
                        @endif
                    </p>

                    {{-- Botones del modal --}}
                    <div class="flex flex-col-reverse gap-2.5 sm:flex-row">
                        <button
                            @if ($modalConfirmacion === 'publicar_ahora')
                                wire:click="guardarBorrador"
                            @else
                                wire:click="cerrarModal"
                            @endif
                            autofocus
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="publicar,programar,guardarBorrador,cancelar"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300
                                   rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                        >
                            @if ($modalConfirmacion === 'publicar_ahora')
                                Guardar como borrador
                            @else
                                Cancelar
                            @endif
                        </button>

                        <button
                            @if ($modalConfirmacion === 'publicar')
                                wire:click="publicar"
                            @elseif ($modalConfirmacion === 'programar')
                                wire:click="programar"
                            @elseif ($modalConfirmacion === 'publicar_ahora')
                                wire:click="publicar"
                            @elseif ($modalConfirmacion === 'borrador')
                                wire:click="guardarBorrador"
                            @else
                                wire:click="cancelar"
                            @endif
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-70 cursor-not-allowed"
                            wire:target="publicar,programar,guardarBorrador,cancelar"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors
                                   focus:outline-none focus:ring-2 focus:ring-offset-2
                                   @if($modalConfirmacion === 'publicar') bg-blue-600 hover:bg-blue-700 focus:ring-blue-500
                                   @elseif($modalConfirmacion === 'programar') bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500
                                   @elseif($modalConfirmacion === 'publicar_ahora') bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500
                                   @elseif($modalConfirmacion === 'borrador') bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-400
                                   @else bg-red-600 hover:bg-red-700 focus:ring-red-500 @endif"
                        >
                            <span wire:loading.remove wire:target="publicar,programar,guardarBorrador,cancelar">
                                @if ($modalConfirmacion === 'publicar')
                                    {{ $modo === 'editar' ? 'Actualizar' : 'Publicar' }}
                                @elseif ($modalConfirmacion === 'programar')
                                    Programar
                                @elseif ($modalConfirmacion === 'publicar_ahora')
                                    Publicar ahora
                                @elseif ($modalConfirmacion === 'borrador')
                                    Guardar
                                @else
                                    Salir
                                @endif
                            </span>
                            <span wire:loading wire:target="publicar,programar,guardarBorrador,cancelar" class="inline-flex items-center gap-1.5">
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
    {{--                                                                    --}}
    {{-- Sustituye los errores de validación en línea cuando se intenta     --}}
    {{-- publicar sin completar los campos obligatorios: lista de un        --}}
    {{-- vistazo todo lo que falta, con un único botón "Cerrar".            --}}
    {{--                                                                    --}}
    {{-- ACCESIBILIDAD: role="dialog", aria-modal, aria-labelledby/describedby, --}}
    {{--  cierre con Escape y clic fuera, autofocus en el botón "Cerrar".   --}}
    {{-- SEGURIDAD XSS: $camposFaltantes proviene de $validator->errors()   --}}
    {{--  (mensajes definidos en el propio componente, nunca de input        --}}
    {{--  arbitrario del cliente) y se imprime exclusivamente con {{ }}.    --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    @if ($mostrarModalCamposFaltantes)
        <div
            wire:key="modal-campos-faltantes"
            wire:click.self="cerrarModalCamposFaltantes"
            wire:keydown.escape.window="cerrarModalCamposFaltantes"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
            aria-hidden="false"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-faltantes-titulo"
                aria-describedby="modal-faltantes-desc"
                class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden"
            >
                <div class="h-1.5 w-full bg-amber-500"></div>

                <div class="p-6 sm:p-7">
                    {{-- Ícono central de advertencia --}}
                    <div class="flex justify-center mb-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-100">
                            <x-admin-icon name="exclamation-triangle" class="w-6 h-6 text-amber-600" />
                        </div>
                    </div>

                    {{-- Título --}}
                    <h2 id="modal-faltantes-titulo" class="text-base font-semibold text-gray-900 text-center mb-2">
                        No se puede publicar todavía
                    </h2>

                    {{-- Descripción --}}
                    <p id="modal-faltantes-desc" class="text-sm text-gray-500 text-center leading-relaxed mb-4">
                        Completa los siguientes campos obligatorios antes de publicar la noticia:
                    </p>

                    {{--
                        Lista de campos faltantes — cada mensaje se imprime con {{ }}
                        (escape XSS automático de Blade) y proviene exclusivamente
                        de los mensajes definidos en mensajesValidacionPublicacion().
                    --}}
                    <ul class="mb-6 space-y-2 text-sm text-gray-700">
                        @foreach ($camposFaltantes as $mensaje)
                            <li class="flex items-start gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                                <x-admin-icon name="x-circle" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-px" />
                                <span>{{ $mensaje }}</span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Botón de cierre — única acción disponible en este modal --}}
                    <button
                        wire:click="cerrarModalCamposFaltantes"
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

</div>
