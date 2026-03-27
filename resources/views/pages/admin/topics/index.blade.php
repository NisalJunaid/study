@extends('layouts.admin', ['heading' => 'Manage Topics'])

@section('content')
<x-admin.flash />

<div class="card stack-md" data-bulk-root>
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
            <a href="{{ route('admin.topics.create') }}" class="btn btn-primary">Create Topic</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.topics.bulk-action') }}" data-bulk-form>
            @csrf
            <input type="hidden" name="action" data-bulk-action>
            <input type="hidden" name="delete_confirmation" value="" data-delete-confirmation>

            <div class="bulk-toolbar">
                <label class="actions-inline">
                    <span class="muted text-sm">Action</span>
                    <select data-bulk-choice>
                        <option value="">Choose bulk action</option>
                        <option value="delete">Delete selected</option>
                        <option value="update">Update selected</option>
                    </select>
                </label>
                <button type="button" class="btn" data-bulk-run>Continue</button>
                <span class="selection-count" data-selection-count>0 selected</span>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th><input type="checkbox" class="table-checkbox" data-select-all></th>
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
                            <td><input type="checkbox" class="table-checkbox" name="ids[]" value="{{ $topic->id }}" data-row-check></td>
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
                            <td class="text-right"><a class="btn" href="{{ route('admin.topics.edit', $topic) }}">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        <div>
            {{ $topics->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" data-bulk-update-modal hidden>
    <div class="modal-card card">
        <h3 class="h2">Update selected topics</h3>
        <p class="muted">Leave a field blank to keep existing values.</p>

        <form method="POST" action="{{ route('admin.topics.bulk-action') }}" class="stack-sm" data-update-form>
            @csrf
            <input type="hidden" name="action" value="update">
            <div data-selected-id-target></div>

            <label class="field">
                <span>Reassign to subject</span>
                <select name="update[subject_id]">
                    <option value="">No change</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Status</span>
                <select name="update[is_active]">
                    <option value="">No change</option>
                    <option value="1">Set active</option>
                    <option value="0">Set inactive</option>
                </select>
            </label>

            <label class="field">
                <span>Sort order</span>
                <input type="number" name="update[sort_order]" min="0" step="1" placeholder="No change">
            </label>

            <div class="actions-inline" style="justify-content:flex-end;">
                <button type="button" class="btn" data-update-cancel>Cancel</button>
                <button type="submit" class="btn btn-primary">Update selected</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-bulk-root]');
    if (!root) return;
    const overlayApi = window.FocusOverlay;
    const rowChecks = [...root.querySelectorAll('[data-row-check]')];
    const selectAll = root.querySelector('[data-select-all]');
    const countEl = root.querySelector('[data-selection-count]');
    const actionSelect = root.querySelector('[data-bulk-choice]');
    const bulkRun = root.querySelector('[data-bulk-run]');
    const bulkForm = root.querySelector('[data-bulk-form]');
    const bulkAction = root.querySelector('[data-bulk-action]');
    const deleteConfirmation = root.querySelector('[data-delete-confirmation]');
    const modal = document.querySelector('[data-bulk-update-modal]');
    const selectedTarget = modal?.querySelector('[data-selected-id-target]');

    const selectedIds = () => rowChecks.filter((item) => item.checked).map((item) => item.value);
    const syncCount = () => {
        const count = selectedIds().length;
        countEl.textContent = `${count} selected`;
        if (selectAll) selectAll.checked = rowChecks.length > 0 && rowChecks.every((item) => item.checked);
    };

    selectAll?.addEventListener('change', () => { rowChecks.forEach((item) => item.checked = selectAll.checked); syncCount(); });
    rowChecks.forEach((item) => item.addEventListener('change', syncCount));

    const setSelectedInputs = (container) => { container.innerHTML = selectedIds().map((id) => `<input type="hidden" name="ids[]" value="${id}">`).join(''); };
    const closeModal = () => { modal.hidden = true; document.body.classList.remove('overlay-open'); };

    modal?.querySelector('[data-update-cancel]')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });

    bulkRun?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) return overlayApi?.show({ title: 'No rows selected', message: 'Select at least one topic first.', variant: 'warning', primary_label: 'Okay' });

        if (actionSelect.value === 'delete') {
            bulkAction.value = 'delete';
            if (deleteConfirmation) deleteConfirmation.value = '';
            const confirmed = await overlayApi?.confirm({ title: 'Delete selected topics', message: 'Delete selected topics?', variant: 'danger', primary_label: 'Delete selected', secondary_label: 'Cancel' });
            if (!confirmed) return;
            if (deleteConfirmation) deleteConfirmation.value = '1';
            return bulkForm.submit();
        }

        if (actionSelect.value === 'update') {
            if (deleteConfirmation) deleteConfirmation.value = '';
            setSelectedInputs(selectedTarget);
            modal.hidden = false;
            document.body.classList.add('overlay-open');
            return;
        }

        overlayApi?.show({ title: 'Choose an action', message: 'Select a bulk action before continuing.', variant: 'warning', primary_label: 'Okay' });
    });

    syncCount();
})();
</script>
@endpush
