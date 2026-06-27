{{-- Contenedor con polling cada 30s para métricas en tiempo real --}}
<div wire:poll.30s class="grid md:grid-cols-3 gap-6">

    {{-- ── Tarjeta: Total de Visitas ── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <x-admin-icon name="arrow-trending-up" class="w-6 h-6 text-blue-600" />
            </div>
            <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
                En vivo
            </span>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1">
            {{ number_format($totalVisitas) }}
        </p>
        <p class="text-sm text-gray-500">Total de visitas al sitio</p>
    </div>

    {{-- ── Tarjeta: Total de Usuarios Registrados ── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <x-admin-icon name="users" class="w-6 h-6 text-purple-600" />
            </div>
            <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
                En vivo
            </span>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1">
            {{ number_format($totalUsuarios) }}
        </p>
        <p class="text-sm text-gray-500">Total de usuarios registrados</p>
    </div>

    {{-- ── Tarjeta: Total de Socios Colaboradores ── --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <x-admin-icon name="user-group" class="w-6 h-6 text-green-600" />
            </div>
            <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">
                En vivo
            </span>
        </div>
        <p class="text-3xl font-bold text-gray-900 mb-1">
            {{ number_format($totalColaboradores) }}
        </p>
        <p class="text-sm text-gray-500">Total de socios colaboradores</p>
    </div>

</div>
