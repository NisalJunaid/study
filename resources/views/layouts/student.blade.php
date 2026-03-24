@extends('layouts.app-shell', [
    'title' => $title ?? 'Student Area | Focus Lab',
    'navDescription' => 'Student workspace',
    'heading' => $heading ?? 'Student dashboard',
    'subheading' => $subheading ?? 'Track your progress and keep improving.',
    'minimalHeader' => $minimalHeader ?? false,
])

@section('sidebar')
    @include('components.navigation.student')
@endsection
