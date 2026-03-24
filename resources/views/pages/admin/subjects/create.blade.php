@extends('layouts.admin', ['heading' => 'Create Subject', 'subheading' => 'Add a new subject to the curriculum.'])

@section('content')
<x-admin.flash />
@include('pages.admin.subjects._form')
@endsection
