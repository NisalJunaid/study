@extends('layouts.student', ['heading' => 'Progress'])

@section('content')
@php
    $avgScore = $summary['average_score_percentage'];
    $avgAccuracy = $summary['average_accuracy_percentage'];
    $onTimeRate = $summary['on_time_answer_rate'];
    $strongestSubject = $summary['strongest_subject'];
    $weakestSubject = $summary['weakest_subject'];
@endphp

<div class="stack-lg progress-dashboard" id="student-progress-dashboard" data-progress-dashboard data-chart-config='@json($charts)'>
    @if($summary['completed_quizzes'] === 0)
        <section class="card empty-state section-surface-primary">
            <h4>Start your first quiz to unlock progress insights</h4>
            <p class="muted mb-0">Once you complete quizzes, we'll show score trends, weak topics, and timing feedback here.</p>
            <div class="actions-row" style="justify-content:center; margin-top: .9rem;">
                <a class="btn btn-primary" href="{{ route('student.quiz.setup') }}">Build a quiz</a>
            </div>
        </section>
    @else
        <section class="card-grid progress-summary-grid">
            <x-student.metric-card title="Quizzes completed" :value="$summary['completed_quizzes']" :subtitle="$summary['in_progress_quizzes'].' active drafts'" />
            <x-student.metric-card title="Average score" :value="$avgScore !== null ? number_format((float) $avgScore, 1).'%' : '—'" subtitle="Across submitted quizzes" />
            <x-student.metric-card title="Average accuracy" :value="$avgAccuracy !== null ? number_format((float) $avgAccuracy, 1).'%' : '—'" subtitle="Question-level grading" />
            <x-student.metric-card title="On-time answer rate" :value="$onTimeRate !== null ? number_format((float) $onTimeRate, 1).'%' : '—'" :subtitle="$insights['measured_answers'] > 0 ? $insights['measured_answers'].' timed answers tracked' : 'Timing data not available yet'" />
            <x-student.metric-card title="Strongest subject" :value="$strongestSubject?->name ?? 'Not enough data'" :subtitle="$strongestSubject && $strongestSubject->average_score !== null ? number_format((float) $strongestSubject->average_score, 1).'% average' : 'Complete at least 2 quizzes per subject'" />
            <x-student.metric-card title="Needs attention" :value="$weakestSubject?->name ?? 'Not enough data'" :subtitle="$weakestSubject && $weakestSubject->average_score !== null ? number_format((float) $weakestSubject->average_score, 1).'% average' : 'We will identify this soon'" />
        </section>

        <section class="card stack-md section-surface-primary">
            <div class="row-between">
                <div class="section-title">
                    <h2 class="section-heading">Performance trends</h2>
                    <p class="section-intro">Track how scores, accuracy, and quiz volume are evolving over time.</p>
                </div>
                <span class="pill">Live student data</span>
            </div>

            <div class="progress-chart-grid">
                <article class="progress-chart-card card-soft">
                    <div class="row-between">
                        <h3 class="h3">Score trend</h3>
                        <span class="muted text-sm">Last 12 quizzes</span>
                    </div>
                    <canvas height="140" data-progress-chart="scoreTrend"></canvas>
                </article>

                <article class="progress-chart-card card-soft">
                    <div class="row-between">
                        <h3 class="h3">Accuracy trend</h3>
                        <span class="muted text-sm">Question-level</span>
                    </div>
                    <canvas height="140" data-progress-chart="accuracyTrend"></canvas>
                </article>

                <article class="progress-chart-card card-soft">
                    <div class="row-between">
                        <h3 class="h3">Quizzes completed</h3>
                        <span class="muted text-sm">Weekly</span>
                    </div>
                    <canvas height="140" data-progress-chart="quizVolume"></canvas>
                </article>

                <article class="progress-chart-card card-soft">
                    <div class="row-between">
                        <h3 class="h3">On-time answers</h3>
                        <span class="muted text-sm">Pace consistency</span>
                    </div>
                    <canvas height="140" data-progress-chart="timingRatio"></canvas>
                </article>
            </div>
        </section>

        <section class="card-grid-progress">
            <article class="card stack-md section-surface-secondary">
                <div class="row-between">
                    <h2 class="section-heading">Weak areas to focus on</h2>
                    <span class="pill pill-muted">Actionable guidance</span>
                </div>

                @if($weakSubjects->isEmpty() && $weakTopics->isEmpty())
                    <div class="empty-state">
                        <h4>No weak trends identified yet</h4>
                        <p class="muted mb-0">Complete more quizzes across subjects to uncover focused revision opportunities.</p>
                    </div>
                @else
                    <div class="stack-sm">
                        @foreach($weakSubjects as $subject)
                            <article class="card-soft weak-area-card" style="--subject-accent: {{ $subject['color'] }};">
                                <div class="row-between">
                                    <div>
                                        <strong class="row-wrap"><span class="subject-color-dot" style="--subject-accent: {{ $subject['color'] }};" aria-hidden="true"></span>{{ $subject['name'] }}</strong>
                                        <p class="muted mb-0 text-sm">{{ number_format((float) $subject['average_score'], 1) }}% avg · {{ $subject['attempts'] }} attempts</p>
                                    </div>
                                </div>
                                @if(collect($subject['weak_topics'])->isNotEmpty())
                                    <div class="weak-topic-chip-row">
                                        @foreach($subject['weak_topics'] as $topic)
                                            <span class="weak-topic-chip">{{ $topic['name'] }} · {{ number_format((float) $topic['average_score'], 1) }}%</span>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach

                        @if($weakTopics->isNotEmpty())
                            <div class="stack-sm">
                                <p class="muted mb-0 text-sm">Lowest performing topics (minimum 2 graded answers):</p>
                                @foreach($weakTopics as $topic)
                                    <div class="row-between card-soft" style="padding:.65rem .75rem;border-radius:.75rem;">
                                        <strong>{{ $topic->topic_name }}</strong>
                                        <span class="muted">{{ number_format((float) $topic->average_score, 1) }}% · {{ $topic->attempts }} answers</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </article>

            <article class="card stack-md section-surface-tertiary">
                <div class="row-between">
                    <h2 class="section-heading">Subject & topic performance</h2>
                    <span class="pill">Comparison view</span>
                </div>

                @if($subjectPerformance->isEmpty())
                    <div class="empty-state">
                        <h4>No performance yet</h4>
                        <p class="muted mb-0">Your subject-by-subject insights appear after your first completed quiz.</p>
                    </div>
                @else
                    <div class="stack-md">
                        <div class="progress-chart-card card-soft">
                            <canvas height="180" data-progress-chart="subjectComparison"></canvas>
                        </div>

                        <div class="stack-sm">
                            @foreach($subjectPerformance as $subject)
                                @php
                                    $average = $subject->average_score !== null ? round((float) $subject->average_score, 1) : null;
                                    $subjectTopics = $topicPerformance->where('subject_id', $subject->id)->sortBy('average_score')->take(3);
                                @endphp
                                <article class="card-soft" style="padding:.8rem;border-radius:.85rem;">
                                    <div class="row-between">
                                        <strong class="row-wrap"><span class="subject-color-dot" style="--subject-accent: {{ $subject->color }};" aria-hidden="true"></span>{{ $subject->name }}</strong>
                                        <span class="muted text-sm">{{ $average !== null ? number_format($average, 1).'%' : 'Pending' }} · {{ $subject->attempts }} attempts</span>
                                    </div>
                                    <div class="progress-track" style="margin-top:.5rem;">
                                        <div class="progress-fill" style="width: {{ $average !== null ? min(100, max(0, $average)) : 0 }}%; background: linear-gradient(90deg, {{ \App\Models\Subject::colorToRgba($subject->color, 0.65) }}, {{ $subject->color }});"></div>
                                    </div>
                                    @if($subjectTopics->isNotEmpty())
                                        <div class="weak-topic-chip-row" style="margin-top:.55rem;">
                                            @foreach($subjectTopics as $topic)
                                                <span class="weak-topic-chip">{{ $topic->topic_name }} · {{ number_format((float) $topic->average_score, 1) }}%</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>
        </section>

        <section class="card stack-md section-surface-primary">
            <div class="row-between">
                <div>
                    <h2 class="section-heading">Recent activity</h2>
                    <p class="section-intro">Quick preview of your latest submissions.</p>
                </div>
                @if($recentActivityAll->isNotEmpty())
                    <button class="btn" type="button" data-activity-drawer-open>View All</button>
                @endif
            </div>

            @if($recentActivity->isEmpty())
                <div class="empty-state">
                    <h4>No submissions yet</h4>
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
        </section>
    @endif

    <aside class="progress-activity-drawer" data-activity-drawer hidden aria-hidden="true">
        <div class="progress-activity-drawer-overlay" data-activity-drawer-close></div>
        <div class="progress-activity-drawer-panel card" role="dialog" aria-modal="true" aria-label="All recent activity">
            <div class="row-between">
                <h2 class="section-heading">All recent activity</h2>
                <button class="btn" type="button" data-activity-drawer-close>Close</button>
            </div>

            <div class="stack-sm progress-activity-drawer-list">
                @forelse($recentActivityAll as $quiz)
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
                @empty
                    <div class="empty-state">
                        <h4>No completed activity yet</h4>
                    </div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
@endsection
