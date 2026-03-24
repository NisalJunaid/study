@extends('layouts.student', ['heading' => 'Progress', 'subheading' => 'Track strength by subject and topic.'])

@section('content')
<div class="card-grid">
    <article class="card"><h3>Strongest topic</h3><p class="muted">Algebra · 82%</p></article>
    <article class="card"><h3>Needs work</h3><p class="muted">Essay Writing · 54%</p></article>
</div>
@endsection
