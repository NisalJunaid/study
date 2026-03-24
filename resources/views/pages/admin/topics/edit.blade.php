@extends('layouts.admin', ['heading' => 'Edit Topic', 'subheading' => 'Update topic details and assignment.'])

@section('content')
<x-admin.flash />
@include('pages.admin.topics._form', ['topic' => $topic, 'subjects' => $subjects])
@endsection
