@extends('layouts.admin')

@section('title', 'Playlist · '.$panel->name)
@section('heading', 'Playlist · '.$panel->name)

@section('content')
    <div class="mb-4">
        <a href="{{ route('display-panels.index') }}" class="text-sm font-medium text-accent hover:underline">← Voltar para Painéis / TVs</a>
    </div>
    <livewire:display-panel-playlist-manager :panel-id="$panel->id" />
@endsection
