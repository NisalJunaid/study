@extends('layouts.admin', ['heading' => 'Subjects'])

@section('content')
<x-admin.flash />

<div class="card stack-md" data-bulk-root>
    <div class="row-between">
        <h3 class="h2">All subjects</h3>
        <div class="actions-inline">
            <a href="{{ route('admin.data-management.index') }}" class="btn">Data management</a>
            <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">New subject</a>
        </div>
    </div>

    <form method="GET" class="filter-row-wide">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search name or slug">
        <select name="level">
            <option value="">All levels</option>
            @foreach(
                \App\Models\Subject::levels() as $level)
                <option value="{{ $level }}" @selected($filters['level'] === $level)>{{ \App\Models\Subject::levelLabel($level) }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="btn">Apply</button>
        <a href="{{ route('admin.subjects.index') }}" class="btn">Reset</a>
    </form>

    @if($subjects->count() === 0)
        <div class="empty-state">
            <h4>No subjects found</h4>
            <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary">New subject</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.subjects.bulk-action') }}" data-bulk-form data-confirm-title="Delete selected subjects" data-confirm-message="Delete selected subjects? Related authoring content may be affected." data-confirm-variant="danger" data-confirm-primary="Delete selected" data-confirm-secondary="Cancel">
            @csrf
            <input type="hidden" name="action" data-bulk-action>

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
                            <td><input type="checkbox" class="table-checkbox" name="ids[]" value="{{ $subject->id }}" data-row-check></td>
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
                                <a class="btn" href="{{ route('admin.subjects.edit', $subject) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        <div>
            {{ $subjects->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" data-bulk-update-modal hidden>
    <div class="modal-card card">
        <h3 class="h2">Update selected subjects</h3>
        <p class="muted">Leave a field blank to keep existing values.</p>

        <form method="POST" action="{{ route('admin.subjects.bulk-action') }}" data-update-form class="stack-sm">
            @csrf
            <input type="hidden" name="action" value="update">
            <div data-selected-id-target></div>

            <label class="field">
                <span>Level</span>
                <select name="update[level]">
                    <option value="">No change</option>
                    @foreach(\App\Models\Subject::levels() as $level)
                        <option value="{{ $level }}">{{ \App\Models\Subject::levelLabel($level) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Color</span>
                <input type="text" name="update[color]" placeholder="#4f46e5">
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
    const modal = document.querySelector('[data-bulk-update-modal]');
    const selectedTarget = modal?.querySelector('[data-selected-id-target]');

    const selectedIds = () => rowChecks.filter((item) => item.checked).map((item) => item.value);
    const syncCount = () => {
        const count = selectedIds().length;
        countEl.textContent = `${count} selected`;
        if (selectAll) {
            selectAll.checked = rowChecks.length > 0 && rowChecks.every((item) => item.checked);
        }
    };

    selectAll?.addEventListener('change', () => {
        rowChecks.forEach((item) => item.checked = selectAll.checked);
        syncCount();
    });

    rowChecks.forEach((item) => item.addEventListener('change', syncCount));

    const setSelectedInputs = (container) => {
        if (!container) return;
        container.innerHTML = selectedIds().map((id) => `<input type="hidden" name="ids[]" value="${id}">`).join('');
    };

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('overlay-open');
    };

    modal?.querySelector('[data-update-cancel]')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    bulkRun?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) {
            overlayApi?.show({ title: 'No rows selected', message: 'Select at least one subject first.', variant: 'warning', primary_label: 'Okay' });
            return;
        }

        if (actionSelect.value === 'delete') {
            bulkAction.value = 'delete';
            const confirmed = await overlayApi?.confirm({
                title: bulkForm.dataset.confirmTitle,
                message: bulkForm.dataset.confirmMessage,
                variant: bulkForm.dataset.confirmVariant,
                primary_label: bulkForm.dataset.confirmPrimary,
                secondary_label: bulkForm.dataset.confirmSecondary,
            });

            if (!confirmed) return;
            bulkForm.submit();
            return;
        }

        if (actionSelect.value === 'update') {
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
