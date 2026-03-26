@extends('layouts.admin', ['heading' => 'Create Subject'])

@section('content')
<x-admin.flash />
@include('pages.admin.subjects._form')
@endsection
