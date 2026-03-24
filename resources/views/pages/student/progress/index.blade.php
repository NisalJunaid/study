@extends('layouts.student', ['heading' => 'Progress', 'subheading' => 'Track your performance trends and focus on weak areas.'])

@section('content')
<div class="stack-lg">
    <section class="card-grid">
        <x-student.metric-card
            title="Total quizzes"
            :value="$summary['total_quizzes']"
            subtitle="All attempts created"
        />
        <x-student.metric-card
            title="Completed quizzes"
            :value="$summary['completed_quizzes']"
            subtitle="Submitted, grading, or graded"
        />
        <x-student.metric-card
            title="In progress"
            :value="$summary['in_progress_quizzes']"
            subtitle="Drafts you can resume"
        />
        <x-student.metric-card
            title="Average score"
            :value="$summary['average_score_percentage'] !== null ? number_format((float) $summary['average_score_percentage'], 1).'%' : 'Not enough data'"
            subtitle="Calculated from completed quizzes"
        />
    </section>

    <section class="card stack-md">
        <div class="row-between">
            <h3 style="margin:0">Performance by subject</h3>
            <span class="pill">{{ $subjectPerformance->count() }} subjects</span>
        </div>

        @if($subjectPerformance->isEmpty())
            <div class="empty-state">
                <h4>No subject performance yet</h4>
                <p class="muted">Complete quizzes to see per-subject trends and consistency.</p>
            </div>
        @else
            <div class="stack-md">
                @foreach($subjectPerformance as $subject)
                    @php
                        $average = $subject->average_score !== null ? round((float) $subject->average_score, 1) : null;
                    @endphp
                    <div class="stack-sm">
                        <div class="row-between">
                            <strong>{{ $subject->name }}</strong>
                            <span class="muted">{{ $average !== null ? number_format($average, 1).'%' : 'Pending' }} · {{ $subject->attempts }} attempt(s)</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ $average !== null ? min(100, max(0, $average)) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card-grid-progress">
        <article class="card stack-md">
            <div class="row-between">
                <h3 style="margin:0">Weak topics</h3>
                <span class="pill pill-muted">Lowest averages</span>
            </div>

            @if($weakTopics->isEmpty())
                <div class="empty-state">
                    <h4>No weak topics yet</h4>
                    <p class="muted">Once you have graded topic attempts, this list highlights where to focus next.</p>
                </div>
            @else
                <div class="stack-sm">
                    @foreach($weakTopics as $topic)
                        <div class="row-between card-soft" style="padding:.75rem;border-radius:.75rem;">
                            <div>
                                <strong>{{ $topic->name }}</strong>
                                <p class="muted" style="margin:.2rem 0 0">{{ $topic->attempts }} graded answer(s)</p>
                            </div>
                            <strong>{{ number_format((float) $topic->average_score, 1) }}%</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="card stack-md">
            <div class="row-between">
                <h3 style="margin:0">Recent activity</h3>
                <span class="pill">Last 8</span>
            </div>

            @if($recentActivity->isEmpty())
                <div class="empty-state">
                    <h4>No recent submissions</h4>
                    <p class="muted">Submit a quiz to start your learning timeline.</p>
                </div>
            @else
                <div class="stack-sm">
                    @foreach($recentActivity as $quiz)
                        <a class="card-soft progress-activity-item" href="{{ route('student.quiz.results', $quiz) }}">
                            <div>
                                <strong>{{ $quiz->subject?->name ?? 'General quiz' }}</strong>
                                <p class="muted" style="margin:.2rem 0 0">{{ strtoupper($quiz->mode) }} · {{ optional($quiz->submitted_at)->format('M d, Y H:i') }}</p>
                            </div>
                            <strong>
                                {{ $quiz->total_awarded_score !== null
                                    ? number_format((float) $quiz->total_awarded_score, 2).' / '.number_format((float) $quiz->total_possible_score, 2)
                                    : 'Pending' }}
                            </strong>
                        </a>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
</div>
@endsection
