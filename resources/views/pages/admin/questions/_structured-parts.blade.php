@php
    $structuredParts = old('structured_parts', $question?->structuredParts?->map(fn ($part) => [
        'part_label' => $part->part_label,
        'prompt_text' => $part->prompt_text,
        'max_score' => $part->max_score,
        'sample_answer' => $part->sample_answer,
        'marking_notes' => $part->marking_notes,
    ])->all() ?? [
        ['part_label' => 'a', 'prompt_text' => '', 'max_score' => 1, 'sample_answer' => '', 'marking_notes' => ''],
    ]);
@endphp

<div id="structured-fields" class="form-panel">
    <div class="row-between">
        <div class="stack-sm">
            <h3 class="h3">Structured Response Parts</h3>
        </div>
        <button type="button" class="btn" data-add-structured-part>Add Part</button>
    </div>

    @error('structured_parts')<small class="field-error">{{ $message }}</small>@enderror

    <div class="stack-md" id="structured-parts-list">
        @foreach($structuredParts as $index => $part)
            <div class="card card-soft stack-sm" data-structured-part-row>
                <div class="grid-3">
                    <label class="field">
                        <span>Label</span>
                        <input type="text" name="structured_parts[{{ $index }}][part_label]" value="{{ $part['part_label'] ?? '' }}" maxlength="20" data-structured-label required>
                    </label>
                    <label class="field">
                        <span>Marks</span>
                        <input type="number" min="0.25" step="0.25" name="structured_parts[{{ $index }}][max_score]" value="{{ $part['max_score'] ?? 1 }}" data-structured-score required>
                    </label>
                </div>

                <label class="field">
                    <span>Part prompt</span>
                    <textarea rows="3" name="structured_parts[{{ $index }}][prompt_text]" data-structured-prompt required>{{ $part['prompt_text'] ?? '' }}</textarea>
                </label>

                <div class="grid-2">
                    <label class="field">
                        <span>Sample answer</span>
                        <textarea rows="3" name="structured_parts[{{ $index }}][sample_answer]">{{ $part['sample_answer'] ?? '' }}</textarea>
                    </label>
                    <label class="field">
                        <span>Marking notes (optional)</span>
                        <textarea rows="3" name="structured_parts[{{ $index }}][marking_notes]">{{ $part['marking_notes'] ?? '' }}</textarea>
                    </label>
                </div>

                <div class="actions-row" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-danger" data-remove-structured-part>Remove part</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
