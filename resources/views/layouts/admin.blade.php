<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head', ['title' => ($title ?? 'Panel de Administración') . ' — Laguna Verde'])

    {{-- Estilos de Livewire (incluye Alpine.js en v4) --}}
    @livewireStyles

    {{-- Chart.js 4.x para el gráfico de tráfico del dashboard --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>

    {{-- Slot para estilos adicionales de secciones específicas --}}
    @stack('head')
</head>

<body class="bg-gray-50 antialiased">

    {{-- El componente Livewire Dashboard se renderiza aquí como $slot --}}
    {{ $slot }}

    {{-- Scripts de Livewire (Alpine.js bundled en v4) --}}
    @livewireScripts

    {{-- Slot para scripts adicionales de widgets específicos --}}
    @stack('scripts')

</body>

</html>
