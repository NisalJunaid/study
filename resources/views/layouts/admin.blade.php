@extends('layouts.app-shell', [
    'title' => $title ?? 'Admin Area | Focus Lab',
    'navDescription' => 'Admin control center',
    'heading' => $heading ?? 'Admin dashboard',
    'subheading' => $subheading ?? 'Manage curriculum, quality, and student outcomes.',
])

@section('sidebar')
    @include('components.navigation.admin')
@endsection
