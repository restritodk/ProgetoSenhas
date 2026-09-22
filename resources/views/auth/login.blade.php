<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
        <div class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-white shadow-2xl shadow-black/30 lg:grid-cols-[1.1fr_0.9fr]">
            <section class="hidden bg-gradient-to-br from-teal-900 via-slate-900 to-slate-950 p-12 lg:block">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-teal-300">progetoSenhas</p>
                <h1 class="mt-24 max-w-md text-5xl font-semibold tracking-tight text-white">Organize o atendimento com clareza.</h1>
                <p class="mt-6 max-w-md text-lg leading-8 text-slate-300">Acesse seu ambiente seguro para cuidar das filas e das pessoas que esperam por atendimento.</p>
            </section>
            <section class="p-8 text-slate-900 sm:p-12">
                <div class="mb-10 lg:hidden"><p class="text-sm font-semibold uppercase tracking-[0.25em] text-teal-700">progetoSenhas</p></div>
                <h2 class="text-3xl font-semibold tracking-tight">Bem-vindo de volta</h2>
                <p class="mt-2 text-slate-500">Entre com suas credenciais para continuar.</p>
                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div><label for="email" class="block text-sm font-medium">E-mail</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-100"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                    <div><label for="password" class="block text-sm font-medium">Senha</label><input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-teal-600 focus:ring-4 focus:ring-teal-100"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                    <button type="submit" class="w-full rounded-xl bg-teal-700 px-4 py-3 font-semibold text-white transition hover:bg-teal-800 focus:outline-none focus:ring-4 focus:ring-teal-200">Entrar</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
