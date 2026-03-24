@extends('layouts.student', ['heading' => 'Quiz History', 'subheading' => 'Review previous attempts, scores, and grading status.'])

@section('content')
<div class="stack-lg">
    @if($quizzes->isEmpty())
        <section class="empty-state card">
            <h4>No quiz attempts yet</h4>
            <p class="muted">Start your first quiz to build history and unlock progress insights.</p>
            <a class="btn btn-primary" href="{{ route('student.quiz.builder') }}">Build a Quiz</a>
        </section>
    @else
        <section class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th class="text-right">Score</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quizzes as $quiz)
                            @php
                                $scoreLabel = $quiz->total_awarded_score !== null
                                    ? number_format((float) $quiz->total_awarded_score, 2).' / '.number_format((float) $quiz->total_possible_score, 2)
                                    : 'Pending';
                            @endphp
                            <tr>
                                <td>{{ $quiz->subject?->name ?? 'General quiz' }}</td>
                                <td><span class="pill">{{ strtoupper($quiz->mode) }}</span></td>
                                <td>
                                    <span class="pill {{ in_array($quiz->status, [\App\Models\Quiz::STATUS_GRADED, \App\Models\Quiz::STATUS_SUBMITTED], true) ? 'pill-success' : 'pill-muted' }}">
                                        {{ strtoupper($quiz->status) }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $scoreLabel }}</td>
                                <td>{{ optional($quiz->submitted_at ?? $quiz->updated_at)->format('M d, Y H:i') }}</td>
                                <td>
                                    <a class="btn" href="{{ route('student.quiz.results', $quiz) }}">View Results</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            {{ $quizzes->links() }}
        </section>
    @endif
</div>
@endsection
