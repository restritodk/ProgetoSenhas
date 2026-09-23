<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso não autorizado — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-text antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-4 py-12 text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-accent">403</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-text">Acesso não autorizado</h1>
        <p class="mt-3 text-sm leading-6 text-text-muted">
            Seu perfil não possui permissão para acessar este recurso.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold text-text hover:bg-background">
                Voltar
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Ir ao início
            </a>
        </div>
    </main>
</body>
</html>
