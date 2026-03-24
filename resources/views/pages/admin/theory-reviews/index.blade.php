@extends('layouts.admin', ['heading' => 'Theory Reviews', 'subheading' => 'Review and override AI-assisted grading.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <form method="GET" action="{{ route('admin.theory-reviews.index') }}" class="grid" style="gap:1rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;">
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>

            <label style="display:flex;align-items:center;gap:.5rem;">
                <input type="checkbox" name="manual_only" value="1" @checked($filters['manual_only'])>
                <span>Manual review only</span>
            </label>

            <div>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="{{ route('admin.theory-reviews.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </section>

    <section class="card" style="padding:0;overflow:auto;">
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
                        <td><span class="pill">{{ $review->grading_status }}</span></td>
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
    </section>

    {{ $reviews->links() }}
</div>
@endsection
