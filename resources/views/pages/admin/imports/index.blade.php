@extends('layouts.admin', ['heading' => 'Question Imports', 'subheading' => 'Upload CSV files, preview validation, and process imports safely.'])

@section('content')
<div class="stack-lg">
    <div class="card quiz-panel">
        <div class="row-between" style="margin-bottom: 1rem;">
            <div>
                <h3 class="h2">Upload Question CSV</h3>
                <p class="muted text-sm mb-0">Supported format includes both MCQ and theory rows in one file.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="stack-md">
            @csrf

            <div class="field">
                <span>CSV file</span>
                <input type="file" name="csv_file" accept=".csv,text/csv" required>
                @error('csv_file') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="grid-2">
                <label class="checkbox-row">
                    <input type="checkbox" name="allow_create_subjects" value="1" {{ old('allow_create_subjects') ? 'checked' : '' }}>
                    <span>Allow creating missing subjects from CSV</span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="allow_create_topics" value="1" {{ old('allow_create_topics') ? 'checked' : '' }}>
                    <span>Allow creating missing topics within matched subjects</span>
                </label>
            </div>

            <div class="actions-row">
                <button class="btn btn-primary" type="submit">Upload & Validate</button>
            </div>
        </form>
    </div>

    <div class="card quiz-panel">
        <h3 class="h2 mt-0">Recent Imports</h3>
        @if($imports->count() === 0)
            <div class="empty-state">
                <h4>No imports yet</h4>
                <p class="muted" style="margin:0">Upload your first CSV file to preview and import questions.</p>
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
