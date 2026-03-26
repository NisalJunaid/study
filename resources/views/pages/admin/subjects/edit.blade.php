@extends('layouts.admin', ['heading' => 'Edit Subject'])

@section('content')
<x-admin.flash />
@include('pages.admin.subjects._form', ['subject' => $subject])
@endsection
