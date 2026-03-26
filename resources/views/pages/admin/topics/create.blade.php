@extends('layouts.admin', ['heading' => 'Create Topic'])

@section('content')
<x-admin.flash />
@include('pages.admin.topics._form', ['subjects' => $subjects])
@endsection
