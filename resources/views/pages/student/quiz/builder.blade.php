@extends('layouts.student', ['heading' => 'Quiz Builder', 'subheading' => 'Choose subject, topics, mode, and count — then start instantly.'])

@section('content')
@php
    $selectedSubjectId = old('subject_id', request('subject_id'));
    $selectedMode = old('mode', request('mode', 'mixed'));
@endphp

<div class="stack-lg">
    @if($subjects->isEmpty())
        <section class="empty-state">
            <h4>No active subjects available</h4>
            <p class="muted">Ask an admin to activate a subject and publish questions before building a quiz.</p>
        </section>
    @else
    <form class="card stack-md quiz-panel" method="POST" action="{{ route('student.quiz.store') }}">
        @csrf

        <div class="grid-2">
            <label class="field">
                <span>Subject</span>
                <select name="subject_id" required>
                    <option value="">Choose a subject</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((string) $selectedSubjectId === (string) $subject->id)>
                            {{ $subject->name }} ({{ $subject->available_questions_count }} questions)
                        </option>
                    @endforeach
                </select>
                @error('subject_id') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Mode</span>
                <select name="mode" required>
                    @foreach($modes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedMode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('mode') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </div>

        <div class="grid-2">
            <label class="field">
                <span>Question count</span>
                <input type="number" min="1" max="50" name="question_count" value="{{ old('question_count', 10) }}" required>
                @error('question_count') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Difficulty (optional)</span>
                <select name="difficulty">
                    <option value="">Any difficulty</option>
                    @foreach($difficulties as $difficulty)
                        <option value="{{ $difficulty }}" @selected(old('difficulty') === $difficulty)>{{ ucfirst($difficulty) }}</option>
                    @endforeach
                </select>
                @error('difficulty') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </div>

        <div class="field">
            <span>Topics (optional)</span>
            <p class="muted text-sm">Only topics for the selected subject will be used.</p>
            <div class="card-grid">
                @foreach($subjects as $subject)
                    @foreach($subject->topics as $topic)
                        <label class="card card-soft" style="display:flex;align-items:center;gap:.45rem;{{ (string) $selectedSubjectId !== (string) $subject->id ? 'opacity:.45;' : '' }}">
                            <input
                                type="checkbox"
                                name="topic_ids[]"
                                value="{{ $topic->id }}"
                                @checked(in_array($topic->id, old('topic_ids', [])))
                                {{ (string) $selectedSubjectId !== (string) $subject->id ? 'disabled' : '' }}
                            >
                            <span>{{ $topic->name }}</span>
                            <span class="pill" style="margin-left:auto;background: {{ $subject->color ? $subject->color . '22' : '#e0e7ff' }}; color: {{ $subject->color ?: '#3730a3' }}">{{ $subject->name }}</span>
                        </label>
                    @endforeach
                @endforeach
            </div>
            @error('topic_ids') <small class="field-error">{{ $message }}</small> @enderror
        </div>

        <div class="actions-row">
            <a href="{{ route('student.subjects.index') }}" class="btn">Back to subjects</a>
            <button type="submit" class="btn btn-primary">Create quiz</button>
        </div>
    </form>

    <section class="card card-soft">
        <h3 class="h2 mt-0">How quiz assignment works</h3>
        <ul class="muted text-sm mb-0" style="padding-left:1rem;display:grid;gap:.35rem">
            <li>Only published questions are selected.</li>
            <li>Inactive subjects/topics are excluded automatically.</li>
            <li>Mixed mode balances MCQ and theory where possible.</li>
            <li>If there are not enough questions, you will get a clear validation error.</li>
        </ul>
    </section>
    @endif
</div>
@endsection
