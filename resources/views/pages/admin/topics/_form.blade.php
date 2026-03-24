@php
    $topic = $topic ?? null;
    $isEdit = (bool) $topic;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.topics.update', $topic) : route('admin.topics.store') }}" class="stack-lg card quiz-panel">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid-2">
        <label class="field">
            <span>Subject</span>
            <select name="subject_id" required>
                <option value="">Select subject...</option>
                @foreach($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((string) old('subject_id', $topic?->subject_id) === (string) $subject->id)>
                        {{ $subject->name }}
                    </option>
                @endforeach
            </select>
            @error('subject_id')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Sort Order</span>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $topic?->sort_order ?? 0) }}" required>
            @error('sort_order')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <div class="grid-2">
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" value="{{ old('name', $topic?->name) }}" required>
            @error('name')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="field">
            <span>Slug</span>
            <input type="text" name="slug" value="{{ old('slug', $topic?->slug) }}" placeholder="Auto-generated from name if left blank">
            @error('slug')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <label class="field">
        <span>Description</span>
        <textarea name="description" rows="4" placeholder="Optional description">{{ old('description', $topic?->description) }}</textarea>
        @error('description')<small class="field-error">{{ $message }}</small>@enderror
    </label>

    <label class="checkbox-row">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $topic?->is_active ?? true))>
        <span>Active topic</span>
    </label>
    @error('is_active')<small class="field-error">{{ $message }}</small>@enderror

    <div class="actions-row">
        <a href="{{ route('admin.topics.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Update Topic' : 'Create Topic' }}</button>
    </div>
</form>
