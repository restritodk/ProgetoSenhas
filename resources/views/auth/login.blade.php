<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-text antialiased">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
        <div class="grid w-full overflow-hidden rounded-3xl border border-border bg-surface shadow-xl lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden bg-primary p-12 text-white lg:block">
                <p class="text-sm font-semibold tracking-tight">humanaClinica</p>
                <h1 class="mt-20 max-w-md text-4xl font-semibold tracking-tight">Organize o atendimento com clareza.</h1>
                <p class="mt-6 max-w-md text-base leading-7 text-white/80">Acesse o painel administrativo para cuidar das filas e das pessoas que esperam por atendimento.</p>
            </section>
            <section class="p-8 sm:p-12">
                <div class="mb-8 lg:hidden">
                    <p class="text-sm font-semibold text-primary">humanaClinica</p>
                </div>
                <h2 class="text-3xl font-semibold tracking-tight text-text">Bem-vindo de volta</h2>
                <p class="mt-2 text-text-muted">Entre com suas credenciais para continuar.</p>
                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <x-ui.input label="E-mail" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        <x-input-error :messages="$errors->get('email')" />
                    </x-ui.input>
                    <x-ui.input label="Senha" name="password" type="password" required autocomplete="current-password">
                        <x-input-error :messages="$errors->get('password')" />
                    </x-ui.input>
                    <x-ui.button type="submit" class="w-full">Entrar</x-ui.button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
