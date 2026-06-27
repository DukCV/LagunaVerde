{{--
    Sección: Galería Multimedia (slider) — subir, eliminar y reordenar por
    arrastrar y soltar.

    DISEÑO:
     - La zona de carga vive FUERA de wire:ignore: necesita que Livewire
       gestione normalmente el input de archivos y sus mensajes @error.
     - La grilla de miniaturas arrastrables vive DENTRO de wire:ignore +
       Alpine (mediaSorter): el arrastre reordena un array LOCAL en memoria
       sin ningún round-trip al servidor por cada "dragover"; solo al
       soltar (dragend) se llama una única vez a $wire.reordenarMedios().
       wire:ignore evita que un re-render de Livewire ajeno (p.ej. un error
       de validación en otra sección) destruya el estado de arrastre en curso.
     - Tras agregar/eliminar un archivo (operaciones que SÍ pasan por
       Livewire), el componente despacha 'media-items-updated' para
       resincronizar el array local de Alpine con la lista autoritativa del
       servidor.
     - El componente Alpine "mediaSorter" está registrado vía Alpine.data()
       en resources/js/app.js, NO como función global en un <script> de este
       Blade: EventForm se monta vía AJAX dentro de EventsManagement
       (wire:click="crearEvento"/"editarEvento"), y un <script> insertado así
       por Livewire nunca se ejecuta (protección del navegador), lo que
       dejaría "mediaSorter" indefinida y rompería en silencio el
       reordenamiento y los botones de eliminar de esta grilla.

    RENDERIZADO DE MINIATURAS ('item.url'):
     - Archivos NUEVOS (aún sin guardar): para imágenes, EventForm llama a
       TemporaryUploadedFile::temporaryUrl(); para videos, 'url' llega en
       null a propósito (esa llamada lanza una excepción para formatos que
       Livewire no considera previsualizables, p.ej. webm/ogg, y rompía TODO
       el render del lote — ver el docblock de EventForm).
     - Archivos EXISTENTES (ya guardados): 'url' viene de Media::url(), que
       sirve el archivo vía la ruta 'media.show' en vez del enlace simbólico
       de 'storage:link' (poco fiable en hosting compartido como Hostinger).
     - En ambos casos, los videos SIEMPRE muestran el ícono de marcador de
       abajo en vez de una miniatura real — nunca se lee item.url para
       'video', así que un valor null ahí es inofensivo por diseño.
--}}
<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">

    <div class="flex items-start gap-3 mb-5">
        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
            <x-admin-icon name="photo" class="w-4.5 h-4.5 text-blue-600" />
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Galería Multimedia</h2>
            <p class="text-xs text-gray-500 mt-0.5">Imágenes y videos para el carrusel del evento. Arrastra para reordenar.</p>
        </div>
    </div>

    {{-- Zona de carga --}}
    <label
        for="input-medios-evento"
        class="block border-2 border-dashed border-gray-200 hover:border-blue-400 rounded-xl p-8 text-center cursor-pointer transition-colors bg-gray-50 hover:bg-blue-50/30 group mb-5"
    >
        <div class="flex items-center justify-center gap-3 mb-2">
            <div class="w-12 h-12 rounded-xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center transition-colors">
                <x-admin-icon name="photo" class="w-6 h-6 text-blue-400" />
            </div>
            <div class="w-12 h-12 rounded-xl bg-purple-50 group-hover:bg-purple-100 flex items-center justify-center transition-colors">
                <x-admin-icon name="video-camera" class="w-6 h-6 text-purple-400" />
            </div>
        </div>
        <p class="text-sm text-gray-700 mb-1">Arrastra imágenes/videos o haz clic para seleccionar</p>
        <p class="text-xs text-gray-400">JPG, PNG, GIF, MP4, WebM — múltiples archivos permitidos</p>
    </label>
    <input
        type="file"
        id="input-medios-evento"
        wire:model="newSliderUploads"
        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg"
        multiple
        class="hidden"
        aria-label="Seleccionar imágenes o vídeos para la galería"
    >

    <div wire:loading wire:target="newSliderUploads" class="mb-4 flex items-center gap-2 text-xs text-blue-600">
        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span>Subiendo archivos...</span>
    </div>

    @error('newSliderUploads.*')
        <p class="mb-4 text-xs text-red-600">{{ $message }}</p>
    @enderror

    {{-- Grilla de miniaturas arrastrables (Alpine, fuera del ciclo de Livewire) --}}
    <div
        wire:ignore
        x-data="mediaSorter(@js($mediaItems))"
        x-on:media-items-updated.window="items = $event.detail.items"
    >
        <p class="text-xs text-gray-500 mb-3" x-show="items.length > 0" x-cloak>
            <span x-text="items.length"></span> archivo(s) — arrastra para reordenar
        </p>

        <p class="text-xs text-gray-400 border-2 border-dashed border-gray-100 rounded-lg p-6 text-center" x-show="items.length === 0">
            No hay archivos en la galería todavía.
        </p>

        {{--
            Grilla responsiva con celdas de tamaño mínimo garantizado en vez
            de un número fijo de columnas por breakpoint: minmax(10rem, 1fr)
            hace que cada miniatura crezca para ocupar el espacio disponible
            (más fácil de ver/tocar) y el navegador decide cuántas entran por
            fila — nunca se desborda porque aspect-video + object-cover
            limita la celda, sin importar las proporciones originales del
            archivo subido.
        --}}
        <div class="grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-4" x-show="items.length > 0" x-cloak>
            <template x-for="(item, index) in items" :key="item.key">
                <div
                    draggable="true"
                    x-on:dragstart="onDragStart(index)"
                    x-on:dragover.prevent="onDragOver(index)"
                    x-on:dragend="onDragEnd()"
                    :title="item.nombre"
                    class="relative group rounded-lg overflow-hidden border-2 border-transparent hover:border-gray-300 cursor-move transition-all"
                >
                    <div class="aspect-video bg-gray-100">
                        <template x-if="item.tipo === 'imagen'">
                            <img :src="item.url" :alt="item.nombre" class="w-full h-full object-cover pointer-events-none">
                        </template>
                        <template x-if="item.tipo === 'video'">
                            <div class="w-full h-full flex items-center justify-center bg-gray-900 pointer-events-none">
                                <x-admin-icon name="video-camera" class="w-8 h-8 text-gray-400" />
                            </div>
                        </template>
                    </div>

                    {{-- Indicador de tipo --}}
                    <div class="absolute top-1.5 left-1.5 pointer-events-none">
                        <template x-if="item.tipo === 'imagen'">
                            <span class="inline-flex items-center p-1.5 rounded-md text-white bg-blue-500/80">
                                <x-admin-icon name="photo" class="w-3.5 h-3.5" />
                            </span>
                        </template>
                        <template x-if="item.tipo === 'video'">
                            <span class="inline-flex items-center p-1.5 rounded-md text-white bg-purple-500/80">
                                <x-admin-icon name="video-camera" class="w-3.5 h-3.5" />
                            </span>
                        </template>
                    </div>

                    {{--
                        Handle de arrastre: solo es una pista visual (la celda
                        completa ya es draggable="true"), así que se mantiene
                        discreta y solo aparece al pasar el mouse — pero ya
                        con un tamaño más cómodo de leer.
                    --}}
                    <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <x-admin-icon name="bars-3" class="w-5 h-5 text-white drop-shadow" />
                    </div>

                    {{--
                        Botón eliminar: visible siempre a una opacidad baja
                        (no solo en :hover) para que sea descubrible y fácil
                        de tocar en pantallas táctiles, donde no existe un
                        estado de "hover" confiable; al pasar el mouse o
                        enfocar con teclado llega a opacidad completa.
                    --}}
                    <button
                        type="button"
                        x-on:click="item.source === 'existing' ? $wire.marcarMedioParaEliminar(item.id) : $wire.quitarMedioNuevo(item.tmpIndex)"
                        class="absolute bottom-1.5 right-1.5 p-2 bg-red-500/85 hover:bg-red-600 text-white rounded-md opacity-80 group-hover:opacity-100 focus:opacity-100 transition-all"
                        aria-label="Eliminar archivo"
                    >
                        <x-admin-icon name="trash" class="w-4 h-4" />
                    </button>

                    {{-- Número de posición --}}
                    <div class="absolute bottom-1.5 left-1.5 bg-black/55 text-white text-xs font-medium rounded-md px-1.5 py-0.5 leading-4 pointer-events-none" x-text="index + 1"></div>
                </div>
            </template>
        </div>
    </div>
</div>
