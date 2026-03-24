@extends('layouts.admin', ['heading' => 'Admin Dashboard', 'subheading' => 'Monitor system activity and authoring progress.'])

@section('content')
<div class="card-grid">
    <article class="card"><h3>Published questions</h3><p class="muted">124</p></article>
    <article class="card"><h3>Imports in progress</h3><p class="muted">1 active job</p></article>
    <article class="card"><h3>Theory reviews pending</h3><p class="muted">9 answers</p></article>
</div>
@endsection
