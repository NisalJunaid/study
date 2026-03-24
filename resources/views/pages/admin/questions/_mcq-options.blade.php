@php
    $options = old('options', $options ?? [
        ['option_key' => 'A', 'option_text' => ''],
        ['option_key' => 'B', 'option_text' => ''],
    ]);
    $correctOptionKey = old('correct_option_key', $correctOptionKey ?? 'A');
@endphp

<div id="mcq-fields" class="form-panel">
    <div class="row-between">
        <div class="stack-sm">
            <h3 class="h3">MCQ Options</h3>
            <p class="panel-description">Provide at least two options and mark exactly one correct answer.</p>
        </div>
        <button type="button" class="btn" data-add-mcq-option>Add Option</button>
    </div>

    @error('options')<small class="field-error">{{ $message }}</small>@enderror
    @error('correct_option_key')<small class="field-error">{{ $message }}</small>@enderror

    <div class="stack-md" id="mcq-options-list">
        @foreach($options as $index => $option)
            <div class="card card-soft" data-mcq-option-row>
                <div class="grid-3">
                    <label class="field">
                        <span>Option Key</span>
                        <input type="text" name="options[{{ $index }}][option_key]" value="{{ $option['option_key'] ?? '' }}" maxlength="5" placeholder="A" data-option-key required>
                        @error("options.$index.option_key")<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field" style="grid-column: span 2; min-width: 0;">
                        <span>Option Text</span>
                        <textarea name="options[{{ $index }}][option_text]" rows="2" data-option-text required>{{ $option['option_text'] ?? '' }}</textarea>
                        @error("options.$index.option_text")<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <div class="row-between">
                    <label class="checkbox-row">
                        <input type="radio" name="correct_option_key" value="{{ $option['option_key'] ?? '' }}" @checked((string) $correctOptionKey === (string) ($option['option_key'] ?? '')) data-correct-radio>
                        <span>Mark as correct answer</span>
                    </label>
                    <button type="button" class="btn btn-danger" data-remove-mcq-option>Remove</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
