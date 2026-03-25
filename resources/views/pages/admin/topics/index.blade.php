@extends('layouts.admin', ['heading' => 'Manage Topics', 'subheading' => 'Organize topic coverage within subjects.'])

@section('content')
<x-admin.flash />

<div class="card stack-md">
    <div class="row-between">
        <h3 class="h2">Topic Directory</h3>
        <a href="{{ route('admin.topics.create') }}" class="btn btn-primary">+ New Topic</a>
    </div>

    <form method="GET" class="filter-row filter-row-wide">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search name or slug">
        <select name="subject_id">
            <option value="">All subjects</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((string) $filters['subject_id'] === (string) $subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('admin.topics.index') }}" class="btn">Reset</a>
    </form>

    @if($topics->count() === 0)
        <div class="empty-state">
            <h4>No topics found</h4>
            <p class="muted">Create topics and assign them to subjects for better quiz filtering.</p>
            <a href="{{ route('admin.topics.create') }}" class="btn btn-primary">Create Topic</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Topic</th>
                    <th>Subject</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($topics as $topic)
                    <tr>
                        <td>
                            <div class="text-strong">{{ $topic->name }}</div>
                            @if($topic->description)
                                <div class="muted text-sm">{{ \Illuminate\Support\Str::limit($topic->description, 80) }}</div>
                            @endif
                        </td>
                        <td>{{ $topic->subject?->name }}</td>
                        <td><code class="mono">{{ $topic->slug }}</code></td>
                        <td>
                            <span class="pill {{ $topic->is_active ? 'pill-success' : 'pill-muted' }}">
                                {{ $topic->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>{{ $topic->sort_order }}</td>
                        <td class="text-right">
                            <div class="actions-inline">
                                <a class="btn" href="{{ route('admin.topics.edit', $topic) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.topics.destroy', $topic) }}" data-confirm-title="Delete topic" data-confirm-message="Delete this topic?" data-confirm-variant="danger" data-confirm-primary="Delete" data-confirm-secondary="Cancel">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $topics->links() }}
        </div>
    @endif
</div>
@endsection
