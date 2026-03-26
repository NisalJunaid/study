@extends('layouts.admin', ['heading' => 'Imports'])

@section('content')
<div class="stack-lg">
    <div class="card quiz-panel">
        <div class="row-between" style="margin-bottom: 1rem;">
            <div>
                <h3 class="h2">Upload Question Import File</h3>
            </div>
        </div>


        <div class="card card-soft stack-sm" style="margin-bottom: 1rem;">
            <p class="muted mb-0">Import supports CSV and JSON. Use the templates below to match the expected schema.</p>
            <div class="actions-inline">
                <a class="btn" href="{{ route('admin.imports.sample', ['template' => 'general']) }}">Download General Sample CSV</a>
                <a class="btn" href="{{ route('admin.imports.sample', ['template' => 'structured_response']) }}">Download Structured Response Sample CSV</a>
                <a class="btn" href="{{ route('admin.imports.sample', ['format' => 'json', 'template' => 'mcq']) }}">Download Sample MCQ JSON</a>
                <a class="btn" href="{{ route('admin.imports.sample', ['format' => 'json', 'template' => 'theory']) }}">Download Sample Theory JSON</a>
                <a class="btn" href="{{ route('admin.imports.sample', ['format' => 'json', 'template' => 'structured_response']) }}">Download Sample Structured Response JSON</a>
                <a class="btn" href="{{ route('admin.imports.sample', ['format' => 'json', 'template' => 'all']) }}">Download Combined Sample JSON</a>
            </div>
        </div>

        <div class="card card-soft stack-sm" style="margin-bottom: 1rem;">
            <p class="muted mb-0"><strong>Canonical JSON format:</strong> root object with a <code>questions</code> array.</p>
            <details>
                <summary class="text-strong">View Sample MCQ JSON</summary>
                <pre style="margin-top:.5rem; overflow:auto;">{{ $jsonSamples['mcq'] }}</pre>
            </details>
            <details>
                <summary class="text-strong">View Sample Theory JSON</summary>
                <pre style="margin-top:.5rem; overflow:auto;">{{ $jsonSamples['theory'] }}</pre>
            </details>
            <details>
                <summary class="text-strong">View Sample Structured Response JSON</summary>
                <pre style="margin-top:.5rem; overflow:auto;">{{ $jsonSamples['structured_response'] }}</pre>
            </details>
        </div>

        <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf

            <div class="field">
                <span>File</span>
                <input type="file" name="import_file" accept=".csv,.json,text/csv,application/json" required>
                <small class="muted">Accepted formats: .csv, .json (max 5MB)</small>
                @error('import_file') <span class="field-error">{{ $message }}</span> @enderror
                @error('csv_file') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="grid-2">
                <div class="checkbox-row toggle-row">
                    <div class="stack-sm">
                        <div class="text-strong">Create subjects</div>
                    </div>
                    <label class="switch" aria-label="Allow creating missing subjects">
                        <input type="checkbox" name="allow_create_subjects" value="1" {{ old('allow_create_subjects') ? 'checked' : '' }}>
                        <span class="switch-track"></span>
                    </label>
                </div>
                <div class="checkbox-row toggle-row">
                    <div class="stack-sm">
                        <div class="text-strong">Create topics</div>
                    </div>
                    <label class="switch" aria-label="Allow creating missing topics">
                        <input type="checkbox" name="allow_create_topics" value="1" {{ old('allow_create_topics') ? 'checked' : '' }}>
                        <span class="switch-track"></span>
                    </label>
                </div>
            </div>

            <div class="actions-row">
                <button class="btn btn-primary" type="submit">Upload</button>
            </div>
        </form>
    </div>

    <div class="card quiz-panel">
        <h3 class="h2 mt-0">Recent imports</h3>
        @if($imports->count() === 0)
            <div class="empty-state">
                <h4>No imports</h4>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Valid</th>
                        <th>Imported</th>
                        <th>Failed</th>
                        <th>Uploaded By</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($imports as $import)
                        <tr>
                            <td><a href="{{ route('admin.imports.show', $import) }}" class="text-strong" style="color:#4338ca;">#{{ $import->id }}</a></td>
                            <td>{{ $import->file_name }}</td>
                            <td><span class="pill">{{ str_replace('_', ' ', $import->status) }}</span></td>
                            <td>{{ number_format($import->total_rows) }}</td>
                            <td>{{ number_format($import->valid_rows) }}</td>
                            <td>{{ number_format($import->imported_rows) }}</td>
                            <td>{{ number_format($import->failed_rows) }}</td>
                            <td>{{ $import->uploadedBy?->name ?? '—' }}</td>
                            <td>{{ $import->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1rem">{{ $imports->links() }}</div>
        @endif
    </div>
</div>
@endsection
