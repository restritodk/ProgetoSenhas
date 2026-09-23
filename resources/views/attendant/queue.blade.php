@extends('layouts.attendant')

@section('title', 'Fila da Mesa')
@section('heading', 'Fila da Mesa')
@section('subheading', 'Senhas aguardando elegíveis à sua mesa ativa')

@section('content')
    <livewire:attendant-desk-queue />
@endsection
