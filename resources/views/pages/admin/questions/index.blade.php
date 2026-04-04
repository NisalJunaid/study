@extends('layouts.admin', ['heading' => 'Questions'])

@section('content')
<x-admin.flash />

<div class="card stack-md" data-bulk-root>
    <div class="row-between">
        <div>
            <h3 class="h2">Questions</h3>
            <p id="question-bank-live-badge" class="muted text-sm mb-0" style="display:none;"></p>
        </div>
        <div class="actions-inline">
            <a href="{{ route('admin.questions.duplicates') }}" class="btn">Duplicate review</a>
            <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">New question</a>
        </div>
    </div>

    <form method="GET" class="filter-grid">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search questions">

        <select name="subject_id">
            <option value="">All subjects</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((string) $filters['subject_id'] === (string) $subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>

        <select name="topic_id">
            <option value="">All topics</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected((string) $filters['topic_id'] === (string) $topic->id)>
                    {{ $topic->name }}
                </option>
            @endforeach
        </select>

        <select name="type">
            <option value="">All types</option>
            <option value="mcq" @selected($filters['type'] === 'mcq')>MCQ</option>
            <option value="theory" @selected($filters['type'] === 'theory')>Theory</option>
            <option value="structured_response" @selected($filters['type'] === 'structured_response')>Structured Response</option>
        </select>

        <select name="difficulty">
            <option value="">All difficulties</option>
            @foreach($difficulties as $difficulty)
                <option value="{{ $difficulty }}" @selected($filters['difficulty'] === $difficulty)>{{ ucfirst($difficulty) }}</option>
            @endforeach
        </select>

        <select name="published">
            <option value="">Any status</option>
            <option value="published" @selected($filters['published'] === 'published')>Published</option>
            <option value="unpublished" @selected($filters['published'] === 'unpublished')>Draft</option>
        </select>

        <select name="flag">
            <option value="">Any moderation state</option>
            <option value="flagged" @selected($filters['flag'] === 'flagged')>Any flagged</option>
            @foreach($flagLabels as $flag => $label)
                <option value="{{ $flag }}" @selected($filters['flag'] === $flag)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn">Apply</button>
        <a href="{{ route('admin.questions.index') }}" class="btn">Reset</a>
    </form>

    @if($questions->count() === 0)
        <div class="empty-state">
            <h4>No questions found</h4>
            <a href="{{ route('admin.questions.create') }}" class="btn btn-primary">New question</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.questions.bulk-action') }}" data-bulk-form>
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
                        <th>Question</th>
                        <th>Subject / Topic</th>
                        <th>Type</th>
                        <th>Difficulty</th>
                        <th>Marks</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($questions as $question)
                        <tr>
                            <td><input type="checkbox" class="table-checkbox" name="ids[]" value="{{ $question->id }}" data-row-check></td>
                            <td>
                                <div class="text-strong">{{ \Illuminate\Support\Str::limit($question->question_text, 100) }}</div>
                                @if($question->type === 'mcq')
                                    <div class="muted text-sm">{{ $question->mcqOptions->count() }} options</div>
                                @endif
                                @if($question->type === 'theory')
                                    <div class="muted text-sm">Rubric set</div>
                                @endif
                                @if($question->type === 'structured_response')
                                    <div class="muted text-sm">{{ $question->structuredParts->count() }} structured part(s)</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $question->subject?->name }}</div>
                                <div class="muted text-sm">{{ $question->topic?->name ?? '—' }}</div>
                            </td>
                            <td>
                                <span class="pill">{{ strtoupper($question->type) }}</span>
                            </td>
                            <td>{{ $question->difficulty ? ucfirst($question->difficulty) : '—' }}</td>
                            <td>{{ number_format((float) $question->marks, 2) }}</td>
                            <td>
                                <span class="pill {{ $question->is_published ? 'pill-success' : 'pill-muted' }}">
                                    {{ $question->is_published ? 'Published' : 'Draft' }}
                                </span>
                                @if(!empty($question->moderationFlags()))
                                    <div class="stack-sm mt-8">
                                        @foreach($question->moderationFlags() as $flag)
                                            <span class="pill pill-warning">{{ $flagLabels[$flag] ?? $flag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-right"><a class="btn" href="{{ route('admin.questions.edit', $question) }}">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        <div>
            {{ $questions->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" data-bulk-update-modal hidden aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1">
    <div class="modal-card card">
        <div class="modal-card-header">
            <h3 class="h2">Update selected questions</h3>
            <button type="button" class="modal-close" data-update-close aria-label="Close update selected questions modal">×</button>
        </div>
        <p class="muted">Leave fields blank to keep existing values. Bulk type changes are intentionally disabled for safety.</p>

        <form method="POST" action="{{ route('admin.questions.bulk-action') }}" class="stack-sm" data-update-form>
            @csrf
            <input type="hidden" name="action" value="update">
            <div data-selected-id-target></div>

            <label class="field">
                <span>Subject</span>
                <select name="update[subject_id]">
                    <option value="">No change</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Topic</span>
                <select name="update[topic_id]">
                    <option value="">No change</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Difficulty</span>
                <select name="update[difficulty]">
                    <option value="">No change</option>
                    @foreach($difficulties as $difficulty)
                        <option value="{{ $difficulty }}">{{ ucfirst($difficulty) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>Publish status</span>
                <select name="update[is_published]">
                    <option value="">No change</option>
                    <option value="1">Published</option>
                    <option value="0">Draft</option>
                </select>
            </label>

            <label class="field">
                <span>Marks</span>
                <input type="number" name="update[marks]" min="0" step="0.25" placeholder="No change">
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
            const badgeEl = document.getElementById('question-bank-live-badge');
            const teardown = window.createRealtimeChannel?.('admin.questions', {
                'question.bank.changed': (payload) => {
                    if (!badgeEl) {
                        return;
                    }

                    const action = String(payload.action || 'updated').replaceAll('_', ' ');
                    badgeEl.style.display = 'block';
                    badgeEl.textContent = `Question bank ${action} · ${payload.published_total ?? 0} published of ${payload.question_total ?? 0}. Refresh to update.`;
                },
            });

            window.addEventListener('beforeunload', () => {
                if (typeof teardown === 'function') {
                    teardown();
                }
            });
        })();

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
                if (bulkRun) bulkRun.disabled = count === 0;
                if (selectAll) selectAll.checked = rowChecks.length > 0 && rowChecks.every((item) => item.checked);
            };

            selectAll?.addEventListener('change', () => { rowChecks.forEach((item) => item.checked = selectAll.checked); syncCount(); });
            rowChecks.forEach((item) => item.addEventListener('change', syncCount));

            const setSelectedInputs = (container) => { container.innerHTML = selectedIds().map((id) => `<input type="hidden" name="ids[]" value="${id}">`).join(''); };
            const closeModal = () => {
                if (!modal) return;
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overlay-open');
            };

            modal?.querySelector('[data-update-cancel]')?.addEventListener('click', closeModal);
            modal?.querySelector('[data-update-close]')?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || modal?.hidden) return;
                closeModal();
            });

            bulkRun?.addEventListener('click', async () => {
                const ids = selectedIds();
                if (ids.length === 0) return overlayApi?.show({ title: 'No rows selected', message: 'Select at least one question first.', variant: 'warning', primary_label: 'Okay' });

                if (actionSelect.value === 'delete') {
                    bulkAction.value = 'delete';
                    if (deleteConfirmation) deleteConfirmation.value = '';
                    const confirmed = await overlayApi?.confirm({ title: 'Delete selected questions', message: 'Delete selected questions? This removes authoring records.', variant: 'danger', primary_label: 'Delete selected', secondary_label: 'Cancel' });
                    if (!confirmed) return;
                    if (deleteConfirmation) deleteConfirmation.value = '1';
                    return bulkForm.submit();
                }

                if (actionSelect.value === 'update') {
                    if (deleteConfirmation) deleteConfirmation.value = '';
                    setSelectedInputs(selectedTarget);
                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overlay-open');
                    modal.focus();
                    return;
                }

                overlayApi?.show({ title: 'Choose an action', message: 'Select a bulk action before continuing.', variant: 'warning', primary_label: 'Okay' });
            });

            closeModal();
            syncCount();
        })();
    </script>
@endpush
