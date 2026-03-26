@extends('layouts.app-shell', [
    'title' => $title ?? 'Admin Area | Focus Lab',
    'navDescription' => 'Admin',
    'heading' => $heading ?? 'Admin dashboard',
    'subheading' => $subheading ?? null,
])

@section('sidebar')
    @include('components.navigation.admin')
@endsection
