<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $title ?? config('app.name', 'Laguna Verde') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Optimización de assets: Preconexión para fuentes y DNS prefetch --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="dns-prefetch" href="https://fonts.bunny.net">

<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

{{--
    Editor de contenido enriquecido (Trix), vía tonysm/rich-text-laravel.
    - <x-rich-text::styles /> publica el CSS base de Trix (toolbar, trix-content).
    - El <script type="module"> define los custom elements <trix-editor> y
      <trix-toolbar>; sin él el editor no se inicializa. Se incluye aquí
      (en ambos layouts, admin y público) para que las páginas de detalle de
      noticia también puedan renderizar .trix-content con sus estilos.
    - Se cargan ANTES de app.css para que nuestros estilos en app.css
      (con igual especificidad) sobrescriban los valores por defecto de Trix.
--}}
<x-rich-text::styles />
<script type="module" src="{{ app(\Tonysm\RichTextLaravel\AssetsManager::class)->url('/trix.esm.js') }}"></script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
