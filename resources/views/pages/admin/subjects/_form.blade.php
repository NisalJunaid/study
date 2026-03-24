@php
    $subject = $subject ?? null;
    $isEdit = (bool) $subject;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}" class="stack-lg card quiz-panel form-shell">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid-2">
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" value="{{ old('name', $subject?->name) }}" required>
            @error('name')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Slug</span>
            <input type="text" name="slug" value="{{ old('slug', $subject?->slug) }}" placeholder="Auto-generated from name if left blank">
            @error('slug')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Level</span>
            <select name="level" required>
                @foreach(\App\Models\Subject::levels() as $level)
                    <option value="{{ $level }}" @selected(old('level', $subject?->level ?? \App\Models\Subject::LEVEL_O) === $level)>{{ \App\Models\Subject::levelLabel($level) }}</option>
                @endforeach
            </select>
            @error('level')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <label class="field">
        <span>Description</span>
        <textarea name="description" rows="4" placeholder="Optional description">{{ old('description', $subject?->description) }}</textarea>
        @error('description')<small class="field-error">{{ $message }}</small>@enderror
    </label>

    <div class="grid-3">
        <label class="field">
            <span>Color</span>
            <input type="text" name="color" value="{{ old('color', $subject?->color) }}" placeholder="#4f46e5 or token">
            @error('color')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Icon</span>
            <input type="text" name="icon" value="{{ old('icon', $subject?->icon) }}" placeholder="book-open">
            @error('icon')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Sort Order</span>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $subject?->sort_order ?? 0) }}" required>
            @error('sort_order')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <div class="checkbox-row toggle-row">
        <div class="stack-sm">
            <div class="text-strong">Active subject</div>
            <small class="muted">Controls whether students can use this subject in quiz building.</small>
        </div>
        <input type="hidden" name="is_active" value="0">
        <label class="switch" aria-label="Toggle subject active state">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $subject?->is_active ?? true))>
            <span class="switch-track"></span>
        </label>
    </div>
    @error('is_active')<small class="field-error">{{ $message }}</small>@enderror

    <div class="actions-row">
        <a href="{{ route('admin.subjects.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Update Subject' : 'Create Subject' }}</button>
    </div>
</form>
