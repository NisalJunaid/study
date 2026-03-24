@extends('layouts.admin', ['heading' => 'Edit Question', 'subheading' => 'Refine wording, difficulty, and grading metadata.'])

@section('content')
<x-admin.flash />

@include('pages.admin.questions._form', ['question' => $question])
@endsection
