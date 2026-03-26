@extends('layouts.admin', ['heading' => 'Edit Question'])

@section('content')
<x-admin.flash />

@include('pages.admin.questions._form', ['question' => $question])
@endsection
