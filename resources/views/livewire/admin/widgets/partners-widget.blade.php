<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">

    {{-- Cabecera --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Socios Colaboradores</h2>
        @if($totalSocios > 5)
            <button class="text-sm text-blue-600 hover:text-blue-700 transition-colors">
                Ver todos ({{ $totalSocios }})
            </button>
        @endif
    </div>

    {{-- Lista de socios --}}
    <div class="space-y-3">
        @forelse($socios as $socio)
            <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">

                {{-- Avatar o iniciales --}}
                @if($socio->profilePhotoUrl())
                    <img
                        src="{{ $socio->profilePhotoUrl() }}"
                        alt="Foto de {{ $socio->name }}"
                        class="w-12 h-12 rounded-lg object-cover flex-shrink-0"
                    >
                @else
                    <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-green-700 text-sm font-semibold">
                            {{ $socio->getInitials() }}
                        </span>
                    </div>
                @endif

                {{-- Información del socio --}}
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-medium text-gray-900 truncate">{{ $socio->name }}</h3>
                    <p class="text-xs text-gray-500 truncate">
                        {{ $socio->interest_area ?? 'Sin área registrada' }}
                        @if($socio->country)
                            · {{ $socio->country }}
                        @endif
                    </p>
                </div>

                {{-- Acciones --}}
                <div class="flex gap-2 flex-shrink-0">
                    <button
                        class="px-3 py-1.5 text-xs bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors"
                        title="Ver detalle del socio"
                    >
                        Ver detalle
                    </button>
                    <button
                        class="p-1.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        title="Editar socio"
                    >
                        <x-admin-icon name="pencil-square" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        @empty
            {{-- Estado vacío: aún no hay usuarios con rol Colaborador --}}
            <div class="text-center py-8">
                <x-admin-icon name="user-group" class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                <p class="text-sm text-gray-500">No hay socios colaboradores registrados aún.</p>
                <p class="text-xs text-gray-400 mt-1">
                    Asigna el rol "Colaborador" a los usuarios socios desde la sección Usuarios.
                </p>
            </div>
        @endforelse
    </div>

</div>
