<!DOCTYPE html>
<html lang="es">

<head>
    @include('partials.head', ['title' => 'Consejo Ciudadano'])
    @livewireStyles
    {{-- Sitio público: siempre modo claro — anula @fluxAppearance para evitar texto blanco
         sobre fondos claros cuando el sistema o localStorage tienen preferencia oscura. --}}
    <script>document.documentElement.classList.remove('dark');</script>
</head>

<body class="pt-24">
    <livewire:header />

    {{-- Modal de inicio de sesión — disponible en todas las páginas públicas --}}
    <livewire:auth.login-modal />

    {{-- Modal de registro — disponible en todas las páginas públicas --}}
    <livewire:auth.register-modal />

    <main>
        {{ $slot }}
    </main>

    <livewire:footer />

    @livewireScripts

    <script>
        Livewire.on('scroll-top', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        Livewire.on('scroll-to', ({
            id
        }) => {
            const el = document.getElementById(id);
            if (el) el.scrollIntoView({
                behavior: 'smooth'
            });
        });
    </script>
</body>

</html>
