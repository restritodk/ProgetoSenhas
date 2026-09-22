@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.card title="Clínica">
            <p class="text-2xl font-semibold text-primary">{{ $clinic?->name ?? 'Não vinculada' }}</p>
            <p class="mt-1 text-sm text-text-muted">{{ $clinic?->active ? 'Ativa' : 'Indisponível' }}</p>
        </x-ui.card>

        <x-ui.card title="Unidades">
            <p class="text-2xl font-semibold text-primary">{{ $unitCount }}</p>
            <p class="mt-1 text-sm text-text-muted">{{ $activeUnitCount }} ativas</p>
        </x-ui.card>

        <x-ui.card title="Usuários">
            @if ($userCount !== null)
                <p class="text-2xl font-semibold text-primary">{{ $userCount }}</p>
                <p class="mt-1 text-sm text-text-muted">Cadastrados na clínica</p>
            @else
                <p class="text-sm text-text-muted">Sem permissão para visualizar usuários.</p>
            @endif
        </x-ui.card>

        <x-ui.card title="Filas e senhas">
            <p class="text-sm text-text-muted">Módulo ainda não configurado</p>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Atendimentos" description="Indicadores operacionais ficarão disponíveis quando o módulo existir.">
            <x-ui.badge tone="warning">Módulo ainda não configurado</x-ui.badge>
        </x-ui.card>
        <x-ui.card title="Painéis e TV" description="Mídia e chamadas de senha serão geridas em uma fase posterior.">
            <x-ui.badge tone="warning">Módulo ainda não configurado</x-ui.badge>
        </x-ui.card>
    </div>
@endsection
