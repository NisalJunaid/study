@extends('layouts.admin', ['heading' => "Import #{$import->id}", 'subheading' => 'Preview row validation results and run import processing.'])

@section('content')
<div class="stack-lg">
    <div class="card">
        <div class="row-between">
            <div>
                <h3 style="margin:0">{{ $import->file_name }}</h3>
                <p class="muted" style="margin:.35rem 0 0">
                    Uploaded by {{ $import->uploadedBy?->name ?? 'Unknown' }} · {{ $import->created_at?->toDayDateTimeString() }}
                </p>
            </div>
            <div class="actions-inline">
                <a class="btn" href="{{ route('admin.imports.index') }}">Back to imports</a>
                @if($import->status === \App\Models\Import::STATUS_READY)
                    <form method="POST" action="{{ route('admin.imports.confirm', $import) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Confirm & Start Import</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card-grid" style="margin-top:1rem;">
            <div class="card card-soft"><div class="muted">Status</div><h3 style="margin:.3rem 0 0">{{ $statusLabels[$import->status] ?? $import->status }}</h3></div>
            <div class="card card-soft"><div class="muted">Total Rows</div><h3 style="margin:.3rem 0 0">{{ number_format($import->total_rows) }}</h3></div>
            <div class="card card-soft"><div class="muted">Valid Rows</div><h3 style="margin:.3rem 0 0">{{ number_format($import->valid_rows) }}</h3></div>
            <div class="card card-soft"><div class="muted">Imported Rows</div><h3 style="margin:.3rem 0 0">{{ number_format($import->imported_rows) }}</h3></div>
            <div class="card card-soft"><div class="muted">Failed Rows</div><h3 style="margin:.3rem 0 0">{{ number_format($import->failed_rows) }}</h3></div>
        </div>

        <div style="margin-top:1rem" class="stack-sm">
            <div class="muted">Import strategy</div>
            <ul style="margin:0; padding-left:1.2rem;">
                <li>Upsert by <strong>subject + topic + type + exact question text</strong>.</li>
                <li>Missing subjects: {{ $import->allow_create_subjects ? 'auto-create enabled' : 'auto-create disabled' }}.</li>
                <li>Missing topics: {{ $import->allow_create_topics ? 'auto-create enabled' : 'auto-create disabled' }}.</li>
            </ul>
            @if($import->error_summary)
                <div class="alert alert-error" style="margin-top:.5rem;">{{ $import->error_summary }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Row Outcomes</h3>

        @if($rows->count() === 0)
            <div class="empty-state">
                <h4>No rows parsed</h4>
                <p class="muted" style="margin:0">This import currently has no row records.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Row #</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Question</th>
                        <th>Subject / Topic</th>
                        <th>Errors</th>
                        <th>Linked Question</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td><span class="pill">{{ $row->status }}</span></td>
                            <td>{{ strtoupper((string) data_get($row->raw_payload, 'type', '')) ?: '—' }}</td>
                            <td style="max-width: 360px;">{{ data_get($row->raw_payload, 'question_text', '—') }}</td>
                            <td>
                                <div>{{ data_get($row->raw_payload, 'subject', '—') }}</div>
                                <div class="muted" style="font-size:.85rem;">{{ data_get($row->raw_payload, 'topic', 'No topic') }}</div>
                            </td>
                            <td style="min-width: 260px;">
                                @if(!empty($row->validation_errors))
                                    <ul style="margin:0; padding-left:1rem;">
                                        @foreach($row->validation_errors as $field => $messages)
                                            @foreach((array) $messages as $message)
                                                <li><strong>{{ $field }}:</strong> {{ $message }}</li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row->related_question_id)
                                    <a href="{{ route('admin.questions.edit', $row->related_question_id) }}" style="color:#4338ca;font-weight:600;">
                                        Question #{{ $row->related_question_id }}
                                    </a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1rem">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
