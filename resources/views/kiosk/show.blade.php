@extends('layouts.kiosk')

@section('title', 'Totem')

@section('content')
    <livewire:public-kiosk :public-token="$publicToken" />
@endsection
