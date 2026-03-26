@extends('layouts.admin', ['heading' => 'Admin Dashboard'])

@section('content')
<div class="page-hero">
    <h2 class="h1">Control center</h2>
</div>
<div class="card-grid">
    <article class="card"><h3 class="h2">Published questions</h3><p class="muted mb-0">124</p></article>
    <article class="card"><h3 class="h2">Imports in progress</h3><p class="muted mb-0">1 active job</p></article>
    <article class="card"><h3 class="h2">Theory reviews pending</h3><p class="muted mb-0">9 answers</p></article>
</div>
@endsection
