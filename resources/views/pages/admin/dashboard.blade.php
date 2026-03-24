@extends('layouts.admin', ['heading' => 'Admin Dashboard', 'subheading' => 'Monitor system activity and authoring progress.'])

@section('content')
<div class="page-hero">
    <h2 class="h1">Control center</h2>
    <p class="mb-0" style="opacity:.9">Track content quality, import operations, and grading workload from one place.</p>
</div>
<div class="card-grid">
    <article class="card"><h3 class="h2">Published questions</h3><p class="muted mb-0">124</p></article>
    <article class="card"><h3 class="h2">Imports in progress</h3><p class="muted mb-0">1 active job</p></article>
    <article class="card"><h3 class="h2">Theory reviews pending</h3><p class="muted mb-0">9 answers</p></article>
</div>
@endsection
