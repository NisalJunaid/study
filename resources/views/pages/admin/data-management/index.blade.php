@extends('layouts.admin', ['heading' => 'Data Management'])

@section('content')
<x-admin.flash />

<div class="card stack-md">
    <div>
        <h3 class="h2">Danger zone</h3>
        <p class="muted mb-0">These operations permanently wipe curriculum and answer datasets. Review scope carefully before continuing.</p>
    </div>

    <div class="grid-2">
        <article class="card stack-sm">
            <h4 class="h3">Current dataset</h4>
            <p class="muted text-sm mb-0">Subjects: {{ $stats['subjects'] }} · Topics: {{ $stats['topics'] }} · Questions: {{ $stats['questions'] }}</p>
            <p class="muted text-sm mb-0">MCQ options: {{ $stats['mcq_options'] }} · Theory rubrics: {{ $stats['theory_meta'] }} · Structured parts: {{ $stats['structured_parts'] }}</p>
            <p class="muted text-sm mb-0">Student answers: {{ $stats['student_answers'] }} · Quiz question links: {{ $stats['quiz_questions'] }}</p>
        </article>

        <article class="card stack-sm">
            <h4 class="h3">Safety notes</h4>
            <ul class="muted text-sm mb-0">
                <li>Wiping questions also deletes dependent quiz-question snapshots and student answers linked by foreign keys.</li>
                <li>Wiping answers removes both authoring answer structures and learner submitted answers.</li>
                <li>Wipe all combines every operation above.</li>
            </ul>
        </article>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Action</th>
                <th>Impact</th>
                <th class="text-right">Execute</th>
            </tr>
            </thead>
            <tbody>
            @foreach([
                'subjects' => 'Delete all subjects and all related topics/questions/answers/quiz links tied to those subjects.',
                'topics' => 'Delete all topics and questions linked to them, plus dependent answers and quiz links.',
                'questions' => 'Delete all questions plus dependent answer records and quiz-question/student-answer rows.',
                'answers' => 'Delete authoring answer content (MCQ options, theory rubrics, structured parts) and student submitted answers.',
                'all' => 'Delete all curriculum and answer data in one operation.',
            ] as $scope => $impact)
                <tr>
                    <td><span class="pill">{{ strtoupper($scope) }}</span></td>
                    <td class="muted text-sm">{{ $impact }}</td>
                    <td class="text-right">
                        <button
                            type="button"
                            class="btn btn-danger"
                            data-wipe-trigger
                            data-scope="{{ $scope }}"
                            data-phrase="{{ $phrases[$scope] }}"
                        >Wipe {{ $scope }}</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" data-wipe-modal hidden>
    <div class="modal-card card">
        <h3 class="h2" data-wipe-title>Confirm wipe</h3>
        <p class="muted" data-wipe-message></p>

        <form method="POST" action="{{ route('admin.data-management.wipe') }}" class="stack-sm">
            @csrf
            <input type="hidden" name="scope" data-wipe-scope>
            <label class="field">
                <span>Type confirmation phrase</span>
                <input type="text" name="confirmation_text" data-wipe-input autocomplete="off" required>
                <small class="muted" data-wipe-phrase-hint></small>
                @error('confirmation_text')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </label>

            <div class="actions-inline" style="justify-content:flex-end;">
                <button type="button" class="btn" data-wipe-cancel>Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm wipe</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.querySelector('[data-wipe-modal]');
    if (!modal) return;

    const title = modal.querySelector('[data-wipe-title]');
    const message = modal.querySelector('[data-wipe-message]');
    const scopeInput = modal.querySelector('[data-wipe-scope]');
    const phraseInput = modal.querySelector('[data-wipe-input]');
    const phraseHint = modal.querySelector('[data-wipe-phrase-hint]');

    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('overlay-open');
        phraseInput.value = '';
    };

    document.querySelectorAll('[data-wipe-trigger]').forEach((button) => {
        button.addEventListener('click', () => {
            const scope = button.dataset.scope;
            const phrase = button.dataset.phrase;
            scopeInput.value = scope;
            title.textContent = `Wipe ${scope}`;
            message.textContent = `This is destructive and cannot be undone. Type ${phrase} to continue.`;
            phraseHint.textContent = `Required phrase: ${phrase}`;
            modal.hidden = false;
            document.body.classList.add('overlay-open');
            phraseInput.focus();
        });
    });

    modal.querySelector('[data-wipe-cancel]')?.addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
})();
</script>
@endpush
