@extends('layouts.admin', ['heading' => 'Theory Review Detail', 'subheading' => 'Inspect AI grading and override when needed.'])

@section('content')
@php
    $snapshot = $review->quizQuestion->question_snapshot ?? [];
    $theoryMeta = $snapshot['theory_meta'] ?? [];
@endphp

<div class="stack-lg">
    <section class="card stack-md">
        <div class="row-between">
            <h3 style="margin:0">Review #{{ $review->id }}</h3>
            <span class="pill">{{ $review->grading_status }}</span>
        </div>

        <p class="muted" style="margin:0">Student: {{ $review->user?->name }} ({{ $review->user?->email }})</p>
        <p class="muted" style="margin:0">Quiz #{{ $review->quizQuestion->quiz_id }} · Question {{ $review->quizQuestion->order_no }}</p>
        <p style="margin:0"><strong>Question:</strong> {{ $snapshot['question_text'] ?? '' }}</p>
        <p style="white-space:pre-wrap;margin:0"><strong>Student Answer:</strong> {{ $review->answer_text ?: 'No answer provided.' }}</p>
        <p style="white-space:pre-wrap;margin:0"><strong>Sample Answer:</strong> {{ $theoryMeta['sample_answer'] ?? 'N/A' }}</p>
    </section>

    <section class="card stack-md">
        <h4 style="margin:0">AI Parsed Result</h4>
        @if(!empty($aiParsed))
            <ul class="stack-sm" style="margin:0;padding-left:1rem;">
                <li><strong>Verdict:</strong> {{ $aiParsed['verdict'] ?? 'N/A' }}</li>
                <li><strong>Score:</strong> {{ $aiParsed['score'] ?? 'N/A' }}</li>
                <li><strong>Confidence:</strong> {{ $aiParsed['confidence'] ?? 'N/A' }}</li>
                <li><strong>Matched points:</strong> {{ implode(', ', $aiParsed['matched_points'] ?? []) ?: 'N/A' }}</li>
                <li><strong>Missing points:</strong> {{ implode(', ', $aiParsed['missing_points'] ?? []) ?: 'N/A' }}</li>
                <li><strong>Feedback:</strong> {{ $aiParsed['feedback'] ?? 'N/A' }}</li>
                <li><strong>Flagged for review:</strong> {{ ($aiParsed['should_flag_for_review'] ?? false) ? 'Yes' : 'No' }}</li>
            </ul>
        @else
            <p class="muted" style="margin:0">No parsed AI payload available. Check raw JSON below.</p>
        @endif

        <details class="card card-soft">
            <summary>Raw AI JSON</summary>
            <pre style="white-space:pre-wrap;overflow:auto;margin:0">{{ json_encode($review->ai_result_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </section>

    <section class="card stack-md">
        <h4 style="margin:0">Override Grade</h4>
        <form method="POST" action="{{ route('admin.theory-reviews.update', $review) }}" class="stack-md">
            @csrf
            @method('PUT')

            <label class="field">
                <span>Score (max {{ $review->quizQuestion->max_score }})</span>
                <input type="number" name="score" step="0.01" min="0" max="{{ $review->quizQuestion->max_score }}" value="{{ old('score', $review->score ?? 0) }}" required>
                @error('score')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field">
                <span>Feedback to student</span>
                <textarea name="feedback" rows="4" required>{{ old('feedback', $review->feedback) }}</textarea>
                @error('feedback')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <div class="row-between">
                <p class="muted" style="margin:0">Current grader: {{ $review->grader?->name ?? 'AI / none' }}</p>
                <button type="submit" class="btn btn-primary">Save Override</button>
            </div>
        </form>
    </section>
</div>
@endsection
