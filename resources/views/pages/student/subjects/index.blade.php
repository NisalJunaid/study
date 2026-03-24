@extends('layouts.student', ['heading' => 'Subjects', 'subheading' => 'Browse by subject and optional topic focus.'])

@section('content')
<div class="card-grid">
    <article class="card"><h3>Mathematics</h3><p class="muted">Algebra, Geometry, Trigonometry</p><a class="btn" href="#">View topics</a></article>
    <article class="card"><h3>English Language</h3><p class="muted">Grammar, Comprehension, Essay Writing</p><a class="btn" href="#">View topics</a></article>
    <article class="card"><h3>Biology</h3><p class="muted">Cells, Genetics, Ecology</p><a class="btn" href="#">View topics</a></article>
</div>
@endsection
