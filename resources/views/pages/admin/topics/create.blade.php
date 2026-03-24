@extends('layouts.admin', ['heading' => 'Create Topic', 'subheading' => 'Add a topic under a subject.'])

@section('content')
<x-admin.flash />
@include('pages.admin.topics._form', ['subjects' => $subjects])
@endsection
