<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpsertQuestionAction;
use App\Events\QuestionBankChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkQuestionActionRequest;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Question::class);

        $query = Question::query()
            ->with([
                'subject:id,name',
                'topic:id,name',
                'mcqOptions:id,question_id,option_key,is_correct',
                'theoryMeta:id,question_id,max_score',
                'structuredParts:id,question_id,part_label,max_score',
            ]);

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('question_text', 'like', "%{$search}%")
                    ->orWhere('explanation', 'like', "%{$search}%");
            });
        }

        if ($subjectId = $request->integer('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($topicId = $request->integer('topic_id')) {
            $query->where('topic_id', $topicId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($difficulty = $request->string('difficulty')->toString()) {
            $query->where('difficulty', $difficulty);
        }

        if ($published = $request->string('published')->toString()) {
            if ($published === 'published') {
                $query->where('is_published', true);
            }

            if ($published === 'unpublished') {
                $query->where('is_published', false);
            }
        }

        $questions = $query
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('pages.admin.questions.index', [
            'questions' => $questions,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'subject_id']),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'subject_id' => $request->integer('subject_id') ?: '',
                'topic_id' => $request->integer('topic_id') ?: '',
                'type' => $request->string('type')->toString(),
                'difficulty' => $request->string('difficulty')->toString(),
                'published' => $request->string('published')->toString(),
            ],
            'difficulties' => ['easy', 'medium', 'hard'],
        ]);
    }


    public function bulkAction(BulkQuestionActionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        if ($validated['action'] === 'delete') {
            $deleted = Question::query()->whereIn('id', $ids)->delete();

            if ($deleted > 0) {
                QuestionBankChanged::dispatch('bulk_deleted');
            }

            return redirect()
                ->route('admin.questions.index')
                ->with('success', sprintf('%d question(s) deleted.', $deleted));
        }

        $updates = array_filter($validated['update'] ?? [], fn ($value) => $value !== null && $value !== '');

        if ($updates === []) {
            return redirect()
                ->route('admin.questions.index')
                ->with('error', 'Select at least one question field to update.');
        }

        if (isset($updates['subject_id']) && array_key_exists('topic_id', $updates) && $updates['topic_id']) {
            $topicValid = Topic::query()
                ->whereKey($updates['topic_id'])
                ->where('subject_id', $updates['subject_id'])
                ->exists();

            if (! $topicValid) {
                return redirect()
                    ->route('admin.questions.index')
                    ->with('error', 'Selected topic does not belong to the selected subject.');
            }
        }

        $updates['updated_by'] = (int) $request->user()->id;
        $updated = Question::query()->whereIn('id', $ids)->update($updates);

        if ($updated > 0) {
            QuestionBankChanged::dispatch('bulk_updated');
        }

        return redirect()
            ->route('admin.questions.index')
            ->with('success', sprintf('%d question(s) updated.', $updated));
    }

    public function create(): View
    {
        $this->authorize('create', Question::class);

        return view('pages.admin.questions.create', [
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'subject_id']),
            'difficulties' => ['easy', 'medium', 'hard'],
        ]);
    }

    public function store(StoreQuestionRequest $request, UpsertQuestionAction $upsertQuestionAction): RedirectResponse
    {
        $question = $upsertQuestionAction->execute(
            payload: $request->validated(),
            user: $request->user(),
            image: $request->file('question_image')
        );
        QuestionBankChanged::dispatch('created', $question->id);

        return redirect()
            ->route('admin.questions.edit', $question)
            ->with('success', 'Question created successfully.');
    }

    public function edit(Question $question): View
    {
        $this->authorize('update', $question);

        $question->load([
            'mcqOptions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'theoryMeta',
            'structuredParts',
        ]);

        return view('pages.admin.questions.edit', [
            'question' => $question,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name', 'subject_id']),
            'difficulties' => ['easy', 'medium', 'hard'],
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question, UpsertQuestionAction $upsertQuestionAction): RedirectResponse
    {
        $this->authorize('update', $question);

        $upsertQuestionAction->execute(
            payload: $request->validated(),
            user: $request->user(),
            question: $question,
            image: $request->file('question_image')
        );
        QuestionBankChanged::dispatch('updated', $question->id);

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        $question->delete();
        QuestionBankChanged::dispatch('deleted', $question->id);

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Question deleted successfully.');
    }

    public function togglePublish(Question $question): RedirectResponse
    {
        $this->authorize('publish', $question);

        $question->update([
            'is_published' => ! $question->is_published,
            'updated_by' => auth()->id(),
        ]);
        QuestionBankChanged::dispatch('publish_toggled', $question->id);

        return redirect()
            ->route('admin.questions.index')
            ->with('success', $question->is_published ? 'Question published.' : 'Question moved to draft.');
    }
}
