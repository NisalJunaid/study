@extends('layouts.admin', ['heading' => 'Theory Reviews'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <div class="card-grid">
            <article class="card card-soft">
                <h3 class="h3">Waiting manual review</h3>
                <p class="muted mb-0">{{ $summary['waiting_manual_review'] }}</p>
            </article>
            <article class="card card-soft">
                <h3 class="h3">Grading failures</h3>
                <p class="muted mb-0">{{ $summary['ai_failed'] }}</p>
            </article>
            <article class="card card-soft">
                <h3 class="h3">Oldest outstanding review</h3>
                <p class="muted mb-0">{{ $summary['oldest_outstanding_at'] ? \Illuminate\Support\Carbon::parse($summary['oldest_outstanding_at'])->diffForHumans() : 'None' }}</p>
            </article>
        </div>
    </section>
    <section class="card">
        <form method="GET" action="{{ route('admin.theory-reviews.index') }}" class="filter-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;">
            <label class="field">
                <span>Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>

            <div class="checkbox-row toggle-row">
                <div class="stack-sm">
                    <div class="text-strong">Manual review only</div>
                </div>
                <label class="switch" aria-label="Filter manual review answers">
                    <input type="checkbox" name="manual_only" value="1" @checked($filters['manual_only'])>
                    <span class="switch-track"></span>
                </label>
            </div>

            <label class="field">
                <span>Queue state</span>
                <select name="queue_state">
                    <option value="">All queue states</option>
                    @foreach($queueStates as $stateValue => $stateLabel)
                        <option value="{{ $stateValue }}" @selected($filters['queue_state'] === $stateValue)>{{ $stateLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Sort</span>
                <select name="sort">
                    <option value="updated_desc" @selected($filters['sort'] === 'updated_desc')>Recently updated</option>
                    <option value="oldest_outstanding" @selected($filters['sort'] === 'oldest_outstanding')>Oldest outstanding first</option>
                </select>
            </label>

            <div>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('admin.theory-reviews.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </section>

    <section class="card" style="padding:0;">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Question</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Quiz</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    <tr>
                        <td>
                            <strong>{{ $review->user?->name }}</strong>
                            <div class="muted">{{ $review->user?->email }}</div>
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($review->question?->question_text ?? 'N/A', 80) }}</td>
                        <td>
                            <span class="pill">{{ $review->grading_status }}</span>
                            @if(($review->ai_result_json['manual_review_reason'] ?? null) === "ai_failed" || !empty($review->ai_result_json['error'] ?? null))
                                <div class="muted text-xs">AI failed</div>
                            @elseif(($review->ai_result_json['manual_review_reason'] ?? null) === "low_confidence")
                                <div class="muted text-xs">Low confidence</div>
                            @endif
                        </td>
                        <td>{{ $review->score ?? 'Pending' }} / {{ $review->quizQuestion?->max_score }}</td>
                        <td>#{{ $review->quizQuestion?->quiz_id }} · {{ $review->quizQuestion?->quiz?->status }}</td>
                        <td>{{ optional($review->updated_at)->diffForHumans() }}</td>
                        <td>
                            <a class="btn btn-ghost" href="{{ route('admin.theory-reviews.show', $review) }}">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No theory answers found for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>

    {{ $reviews->links() }}
</div>
@endsection
