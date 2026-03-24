@extends('layouts.admin', ['heading' => 'Create Question', 'subheading' => 'Add MCQ and theory questions to the bank.'])

@section('content')
<x-admin.flash />

@include('pages.admin.questions._form')
@endsection
