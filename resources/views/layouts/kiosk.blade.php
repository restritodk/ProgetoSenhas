<!doctype html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1e3a5f">
    <title>@yield('title', 'Totem') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="kiosk-body min-h-dvh text-text antialiased">
    <div class="kiosk-atmosphere" aria-hidden="true"></div>
    <div class="relative min-h-dvh">
        @yield('content')
    </div>
    @livewireScripts
</body>
</html>
