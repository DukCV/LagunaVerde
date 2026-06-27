<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">

    {{-- Cabecera --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Mensajes</h2>
        <button class="text-sm text-blue-600 hover:text-blue-700 transition-colors">
            Ver todos
        </button>
    </div>

    {{-- Lista de mensajes (datos de demostración) --}}
    <div class="space-y-3">
        @foreach($mensajes as $mensaje)
            <button
                class="w-full flex gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-left"
                aria-label="Mensaje de {{ $mensaje['nombre'] }}"
            >
                {{-- Avatar con iniciales (módulo de mensajería aún sin imágenes reales) --}}
                @php
                    $palabras  = explode(' ', trim($mensaje['nombre']));
                    $iniciales = strtoupper(
                        substr($palabras[0] ?? '', 0, 1) . substr($palabras[1] ?? '', 0, 1)
                    );
                    $colores = ['bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700',
                                'bg-green-100 text-green-700', 'bg-orange-100 text-orange-700'];
                    $color = $colores[$mensaje['id'] % count($colores)];
                @endphp

                <div class="w-10 h-10 rounded-full {{ $color }} flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-semibold">{{ $iniciales }}</span>
                </div>

                {{-- Contenido del mensaje --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="text-sm font-medium text-gray-900 truncate">
                            {{ $mensaje['nombre'] }}
                        </span>
                        {{-- Indicador de no leído --}}
                        @if(! $mensaje['leido'])
                            <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0" aria-label="No leído"></span>
                        @endif
                    </div>

                    <p class="text-xs text-gray-600 line-clamp-2 mb-1">
                        {{ $mensaje['mensaje'] }}
                    </p>

                    <span class="text-xs text-gray-400">{{ $mensaje['tiempo'] }}</span>
                </div>
            </button>
        @endforeach
    </div>

    {{-- Nota de estado del módulo --}}
    <div class="mt-4 pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-400 text-center">
            Módulo de mensajería en desarrollo
        </p>
    </div>

</div>
