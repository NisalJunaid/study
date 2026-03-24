@extends('layouts.admin', ['heading' => 'Question Bank', 'subheading' => 'Manage MCQ and theory questions with structured filters.'])

@section('content')
<x-admin.flash />

<div class="card stack-md">
    <div class="row-between">
        <h3 style="margin:0">Questions</h3>
        <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">+ New Question</a>
    </div>

    <form method="GET" class="filter-grid">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search question text or explanation">

        <select name="subject_id">
            <option value="">All subjects</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((string) $filters['subject_id'] === (string) $subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>

        <select name="topic_id">
            <option value="">All topics</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected((string) $filters['topic_id'] === (string) $topic->id)>
                    {{ $topic->name }}
                </option>
            @endforeach
        </select>

        <select name="type">
            <option value="">All types</option>
            <option value="mcq" @selected($filters['type'] === 'mcq')>MCQ</option>
            <option value="theory" @selected($filters['type'] === 'theory')>Theory</option>
        </select>

        <select name="difficulty">
            <option value="">All difficulties</option>
            @foreach($difficulties as $difficulty)
                <option value="{{ $difficulty }}" @selected($filters['difficulty'] === $difficulty)>{{ ucfirst($difficulty) }}</option>
            @endforeach
        </select>

        <select name="published">
            <option value="">Any publish status</option>
            <option value="published" @selected($filters['published'] === 'published')>Published</option>
            <option value="unpublished" @selected($filters['published'] === 'unpublished')>Draft</option>
        </select>

        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('admin.questions.index') }}" class="btn">Reset</a>
    </form>

    @if($questions->count() === 0)
        <div class="empty-state">
            <h4>No questions found</h4>
            <p class="muted">Try adjusting filters or create a new question to start building your bank.</p>
            <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">Create Question</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Question</th>
                    <th>Subject / Topic</th>
                    <th>Type</th>
                    <th>Difficulty</th>
                    <th>Marks</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($questions as $question)
                    <tr>
                        <td>
                            <div style="font-weight:600">{{ \Illuminate\Support\Str::limit($question->question_text, 100) }}</div>
                            @if($question->type === 'mcq')
                                <div class="muted">{{ $question->mcqOptions->count() }} options</div>
                            @endif
                            @if($question->type === 'theory')
                                <div class="muted">Theory rubric configured</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $question->subject?->name }}</div>
                            <div class="muted">{{ $question->topic?->name ?? 'No topic' }}</div>
                        </td>
                        <td>
                            <span class="pill">{{ strtoupper($question->type) }}</span>
                        </td>
                        <td>{{ $question->difficulty ? ucfirst($question->difficulty) : '—' }}</td>
                        <td>{{ number_format((float) $question->marks, 2) }}</td>
                        <td>
                            <span class="pill {{ $question->is_published ? 'pill-success' : 'pill-muted' }}">
                                {{ $question->is_published ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="actions-inline">
                                <a class="btn" href="{{ route('admin.questions.edit', $question) }}">Edit</a>

                                <form method="POST" action="{{ route('admin.questions.toggle-publish', $question) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn">{{ $question->is_published ? 'Unpublish' : 'Publish' }}</button>
                                </form>

                                <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question? This cannot be undone for authoring.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $questions->links() }}
        </div>
    @endif
</div>
@endsection
