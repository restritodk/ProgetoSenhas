<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Área interna · {{ config('app.name') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900"><main class="mx-auto max-w-5xl px-6 py-12"><div class="flex items-center justify-between"><div><p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Área protegida</p><h1 class="mt-2 text-3xl font-semibold">Olá, {{ auth()->user()->name }}</h1><p class="mt-2 text-slate-600">Seu acesso foi validado com segurança.</p></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-slate-300 bg-white px-4 py-2 font-medium hover:bg-slate-50">Sair</button></form></div></main></body>
</html>
