<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkSubjectActionRequest;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subject::class);

        $query = Subject::query();

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status === 'active') {
                $query->where('is_active', true);
            }

            if ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }

        $subjects = $query
            ->withCount('topics')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('pages.admin.subjects.index', [
            'subjects' => $subjects,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'level' => $request->string('level')->toString(),
            ],
        ]);
    }


    public function bulkAction(BulkSubjectActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        if ($validated['action'] === 'delete') {
            $deleted = Subject::query()->whereIn('id', $ids)->delete();

            return redirect()
                ->route('admin.subjects.index')
                ->with('success', sprintf('%d subject(s) deleted.', $deleted));
        }

        $updates = array_filter($validated['update'] ?? [], fn ($value) => $value !== null && $value !== '');

        if ($updates === []) {
            return redirect()
                ->route('admin.subjects.index')
                ->with('error', 'Select at least one subject field to update.');
        }

        if (array_key_exists('color', $updates)) {
            $updates['color'] = Subject::normalizeColor((string) $updates['color']);
        }

        $updated = Subject::query()->whereIn('id', $ids)->update($updates);

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', sprintf('%d subject(s) updated.', $updated));
    }

    public function create(): View
    {
        $this->authorize('create', Subject::class);

        return view('pages.admin.subjects.create');
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        Subject::create($request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    public function edit(Subject $subject): View
    {
        $this->authorize('update', $subject);

        return view('pages.admin.subjects.edit', [
            'subject' => $subject,
        ]);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $this->authorize('update', $subject);

        $subject->update($request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        return redirect()
            ->route('admin.subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }
}
