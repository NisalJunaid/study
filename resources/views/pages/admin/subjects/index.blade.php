@extends('layouts.admin', ['heading' => 'Manage Subjects', 'subheading' => 'Create and organize subject domains.'])

@section('content')
<x-admin.flash />

<div class="card stack-md">
    <div class="row-between">
        <h3 class="h2">Subject Directory</h3>
        <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">+ New Subject</a>
    </div>

    <form method="GET" class="filter-row-wide">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search name or slug">
        <select name="level">
            <option value="">All levels</option>
            @foreach(\App\Models\Subject::levels() as $level)
                <option value="{{ $level }}" @selected($filters['level'] === $level)>{{ \App\Models\Subject::levelLabel($level) }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="btn">Filter</button>
        <a href="{{ route('admin.subjects.index') }}" class="btn">Reset</a>
    </form>

    @if($subjects->count() === 0)
        <div class="empty-state">
            <h4>No subjects found</h4>
            <p class="muted">Create your first subject to start organizing topics and questions.</p>
            <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">Create Subject</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th>Topics</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($subjects as $subject)
                    <tr>
                        <td>
                            <div class="text-strong">{{ $subject->name }}</div>
                            @if($subject->description)
                                <div class="muted text-sm">{{ \Illuminate\Support\Str::limit($subject->description, 80) }}</div>
                            @endif
                        </td>
                        <td><code class="mono">{{ $subject->slug }}</code></td>
                        <td><span class="pill">{{ \App\Models\Subject::levelLabel($subject->level) }}</span></td>
                        <td>
                            <span class="pill {{ $subject->is_active ? 'pill-success' : 'pill-muted' }}">
                                {{ $subject->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>{{ $subject->sort_order }}</td>
                        <td>{{ $subject->topics_count }}</td>
                        <td class="text-right">
                            <div class="actions-inline">
                                <a class="btn" href="{{ route('admin.subjects.edit', $subject) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject? Related topics/questions may be affected.');">
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
            {{ $subjects->links() }}
        </div>
    @endif
</div>
@endsection
