<x-layouts::principal>

    {{--
        MainEventDetailPage.blade.php
        ─────────────────────────────────────────────────────────────────
        Vista contenedora principal de la página de detalle de evento.

        Recibe $uuid desde el closure de la ruta en web.php y lo pasa
        directamente al componente Livewire — sin lógica adicional aquí.

        Toda la obtención de datos, validación y renderizado es
        responsabilidad de EventDetailPage.
        ─────────────────────────────────────────────────────────────────
    --}}
    <livewire:events.event-detail.event-detail-page :uuid="$uuid" />

</x-layouts::principal>
