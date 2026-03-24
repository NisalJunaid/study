@extends('layouts.admin', ['heading' => 'Edit Subject', 'subheading' => 'Update subject details and visibility.'])

@section('content')
<x-admin.flash />
@include('pages.admin.subjects._form', ['subject' => $subject])
@endsection
