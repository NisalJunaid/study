@extends('layouts.admin', ['heading' => 'Create Question'])

@section('content')
<x-admin.flash />

@include('pages.admin.questions._form')
@endsection
