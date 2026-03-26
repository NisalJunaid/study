@extends('layouts.app-shell', [
    'title' => $title ?? 'Student Area | Focus Lab',
    'navDescription' => 'Student',
    'heading' => $heading ?? 'Student dashboard',
    'subheading' => $subheading ?? null,
    'minimalHeader' => $minimalHeader ?? false,
    'suppressFlash' => $suppressFlash ?? false,
    'contentWidthClass' => $contentWidthClass ?? 'content-shell',
])

@section('sidebar')
    @include('components.navigation.student')
@endsection
