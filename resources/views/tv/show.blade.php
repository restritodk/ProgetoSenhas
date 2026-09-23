@extends('layouts.tv')

@section('title', $panelName)

@section('content')
    <livewire:tv-display :public-token="$publicToken" />
@endsection
