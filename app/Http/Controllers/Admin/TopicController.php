<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTopicRequest;
use App\Http\Requests\Admin\UpdateTopicRequest;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Topic::class);

        $query = Topic::query()->with('subject:id,name');

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('topics.name', 'like', "%{$search}%")
                    ->orWhere('topics.slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            if ($status === 'active') {
                $query->where('topics.is_active', true);
            }

            if ($status === 'inactive') {
                $query->where('topics.is_active', false);
            }
        }

        if ($subjectId = $request->integer('subject_id')) {
            $query->where('topics.subject_id', $subjectId);
        }

        $topics = $query
            ->orderBy('topics.sort_order')
            ->orderBy('topics.name')
            ->paginate(10)
            ->withQueryString();

        return view('pages.admin.topics.index', [
            'topics' => $topics,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'subject_id' => $request->integer('subject_id') ?: '',
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Topic::class);

        return view('pages.admin.topics.create', [
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreTopicRequest $request): RedirectResponse
    {
        Topic::create($request->validated());

        return redirect()
            ->route('admin.topics.index')
            ->with('success', 'Topic created successfully.');
    }

    public function edit(Topic $topic): View
    {
        $this->authorize('update', $topic);

        return view('pages.admin.topics.edit', [
            'topic' => $topic,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateTopicRequest $request, Topic $topic): RedirectResponse
    {
        $this->authorize('update', $topic);

        $topic->update($request->validated());

        return redirect()
            ->route('admin.topics.index')
            ->with('success', 'Topic updated successfully.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $this->authorize('delete', $topic);

        $topic->delete();

        return redirect()
            ->route('admin.topics.index')
            ->with('success', 'Topic deleted successfully.');
    }
}
