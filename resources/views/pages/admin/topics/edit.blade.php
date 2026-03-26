@extends('layouts.admin', ['heading' => 'Edit Topic'])

@section('content')
<x-admin.flash />
@include('pages.admin.topics._form', ['topic' => $topic, 'subjects' => $subjects])
@endsection
