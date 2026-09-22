@extends('layouts.admin')

@section('title', 'Clínica / Unidades')
@section('heading', 'Clínica / Unidades')

@section('content')
    <div class="space-y-6">
        <x-ui.card title="Dados da clínica" description="As alterações valem somente para a clínica do seu acesso.">
            <form method="POST" action="{{ route('clinic.update') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')

                <x-ui.input label="Nome" name="name" value="{{ old('name', $clinic->name) }}" required maxlength="255" autocomplete="organization">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>

                <x-ui.input label="Identificador" name="slug" value="{{ old('slug', $clinic->slug) }}" required maxlength="255">
                    <x-input-error :messages="$errors->get('slug')" />
                </x-ui.input>

                <div class="md:col-span-2">
                    <x-ui.button type="submit">Salvar dados da clínica</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <livewire:units-manager />
    </div>
@endsection
