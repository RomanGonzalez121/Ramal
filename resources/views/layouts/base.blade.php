<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Ramal')</title>
    <meta name="description" content="@yield('descripcion', 'Centro de control de colectivos en vivo. Las líneas son simuladas, las calles y el tiempo real, no.')">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#F5C400">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Aplica el tema antes de pintar para que no haya parpadeo.
        (function () {
            try {
                var t = localStorage.getItem('ramal-tema');
                if (t) document.documentElement.dataset.theme = t === 'noche' ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.iconos')
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 cartel-parada px-4 py-2 font-semibold">Ir al contenido</a>
    @yield('cuerpo')
</body>
</html>
