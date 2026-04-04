@extends('layouts.admin', ['heading' => 'Dashboard'])

@section('content')
<div class="page-hero">
    <h2 class="h1">Operational Dashboard</h2>
    <p class="muted mb-0">Metrics focused on grading throughput, learner activity, and content quality.</p>
</div>

@php
    $operational = $metrics['operational'];
    $contentHealth = $metrics['content_health'];
@endphp

<div class="card-grid">
    <article class="card">
        <h3 class="h2">Quizzes started</h3>
        <p class="muted mb-0">{{ number_format($operational['total_quizzes_started']) }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Quizzes submitted</h3>
        <p class="muted mb-0">{{ number_format($operational['total_quizzes_submitted']) }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Quizzes graded</h3>
        <p class="muted mb-0">{{ number_format($operational['total_quizzes_graded']) }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Pending grading</h3>
        <p class="muted mb-0">{{ number_format($operational['pending_grading_count']) }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Pending manual review</h3>
        <p class="muted mb-0">{{ number_format($operational['pending_manual_review_count']) }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Oldest review age</h3>
        <p class="muted mb-0">{{ $oldestManualReviewAge ?? 'No backlog' }}</p>
    </article>
    <article class="card">
        <h3 class="h2">Active students</h3>
        <p class="muted mb-0">{{ number_format($operational['active_students_recent_count']) }} in last {{ $operational['active_students_window_days'] }} days</p>
    </article>
</div>


@if(!empty($alerts))
<div class="card stack-sm" style="margin-top:1rem;"> 
    <h3 class="h2">Operational alerts</h3>
    <ul class="stack-xs mb-0" style="padding-left:1.1rem;">
        @foreach($alerts as $alert)
            <li>
                <strong>{{ $alert['title'] }}</strong>
                <span class="muted">{{ $alert['message'] }}</span>
            </li>
        @endforeach
    </ul>
</div>
@endif

<div class="card stack-md" style="margin-top:1rem;">
    <h3 class="h2">Content health</h3>
    <div class="table-wrap">
        <table class="table table-sm">
            <tbody>
                <tr>
                    <th scope="row">Published questions</th>
                    <td class="text-right">{{ number_format($contentHealth['published_questions']) }}</td>
                </tr>
                <tr>
                    <th scope="row">Unpublished questions</th>
                    <td class="text-right">{{ number_format($contentHealth['unpublished_questions']) }}</td>
                </tr>
                <tr>
                    <th scope="row">Flagged questions</th>
                    <td class="text-right">{{ number_format($contentHealth['flagged_questions']) }}</td>
                </tr>
                <tr>
                    <th scope="row">Recently imported (7d)</th>
                    <td class="text-right">{{ number_format($contentHealth['recently_imported_questions']) }}</td>
                </tr>
                <tr>
                    <th scope="row">Duplicate suspected</th>
                    <td class="text-right">{{ number_format($contentHealth['duplicate_suspected_questions']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card-grid" style="margin-top:1rem;">
    <article class="card stack-sm">
        <h3 class="h2">Subject performance</h3>
        @if(collect($operational['subject_performance'])->isEmpty())
            <p class="muted mb-0">No graded answer data yet.</p>
        @else
            <div class="table-wrap">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th class="text-right">Attempts</th>
                            <th class="text-right">Avg score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($operational['subject_performance'] as $row)
                            <tr>
                                <td>{{ $row['subject_name'] }}</td>
                                <td class="text-right">{{ number_format($row['attempts']) }}</td>
                                <td class="text-right">{{ number_format($row['average_score'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <article class="card stack-sm">
        <h3 class="h2">Common weak areas</h3>
        @if(collect($operational['weak_areas'])->isEmpty())
            <p class="muted mb-0">No topic has enough attempts to mark as weak yet.</p>
        @else
            <ul class="stack-xs mb-0" style="padding-left:1.1rem;">
                @foreach($operational['weak_areas'] as $row)
                    <li>
                        <strong>{{ $row['subject_name'] }} · {{ $row['topic_name'] }}</strong>
                        <span class="muted">({{ number_format($row['average_score'], 1) }}% avg over {{ number_format($row['attempts']) }} attempts)</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</div>
@endsection
