@extends('layouts.admin', ['heading' => 'Duplicate Review'])

@section('content')
<x-admin.flash />

<div class="card stack-md">
    <div class="row-between">
        <div>
            <h3 class="h2">Suspected Duplicates</h3>
            <p class="muted mb-0">Review duplicates flagged during import or question editing.</p>
        </div>
        <a href="{{ route('admin.questions.index', ['flag' => 'flagged']) }}" class="btn">All flagged questions</a>
    </div>

    @if($questions->isEmpty())
        <div class="empty-state">
            <h4>No suspected duplicates</h4>
            <p class="muted">Great—no duplicate flags are pending right now.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Question</th>
                    <th>Subject / Topic</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($questions as $question)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::limit($question->question_text, 120) }}</td>
                        <td>
                            <div>{{ $question->subject?->name }}</div>
                            <div class="muted text-sm">{{ $question->topic?->name ?? '—' }}</div>
                        </td>
                        <td>
                            @foreach($question->moderationFlags() as $flag)
                                <span class="pill {{ $flag === \App\Models\Question::FLAG_DUPLICATE_SUSPECTED ? 'pill-warning' : '' }}">{{ $flagLabels[$flag] ?? $flag }}</span>
                            @endforeach
                        </td>
                        <td class="text-right">
                            <div class="actions-inline" style="justify-content:flex-end;">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="btn">Open</a>
                                <form method="POST" action="{{ route('admin.questions.dismiss-flag', $question) }}">
                                    @csrf
                                    <input type="hidden" name="flag" value="{{ \App\Models\Question::FLAG_DUPLICATE_SUSPECTED }}">
                                    <button type="submit" class="btn">Dismiss duplicate flag</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $questions->links() }}
    @endif
</div>
@endsection
