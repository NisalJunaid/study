<div id="theory-fields" class="stack-md">
    <h3 style="margin:0">Theory Rubric</h3>

    <label class="field">
        <span>Sample Answer</span>
        <textarea name="sample_answer" rows="5" placeholder="Reference answer for grading" required>{{ old('sample_answer', $question?->theoryMeta?->sample_answer) }}</textarea>
        @error('sample_answer')<small class="field-error">{{ $message }}</small>@enderror
    </label>

    <label class="field">
        <span>Grading Notes</span>
        <textarea name="grading_notes" rows="4" placeholder="Optional rubric notes for graders/AI">{{ old('grading_notes', $question?->theoryMeta?->grading_notes) }}</textarea>
        @error('grading_notes')<small class="field-error">{{ $message }}</small>@enderror
    </label>

    <div class="grid-2">
        <label class="field">
            <span>Keywords (one per line or | separated)</span>
            <textarea name="keywords" rows="4" placeholder="clarity|structure|meaning">{{ old('keywords', collect($question?->theoryMeta?->keywords ?? [])->implode(PHP_EOL)) }}</textarea>
            @error('keywords')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Acceptable Phrases (one per line or | separated)</span>
            <textarea name="acceptable_phrases" rows="4" placeholder="guides pauses|separates ideas">{{ old('acceptable_phrases', collect($question?->theoryMeta?->acceptable_phrases ?? [])->implode(PHP_EOL)) }}</textarea>
            @error('acceptable_phrases')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>
</div>
