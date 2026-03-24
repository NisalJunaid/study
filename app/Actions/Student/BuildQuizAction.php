<?php

namespace App\Actions\Student;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildQuizAction
{
    public function availableQuestionCount(array $subjectIds, array $topicIds, string $mode, ?string $difficulty): int
    {
        $countByType = $this->countByType($subjectIds, $topicIds, $difficulty);

        return match ($mode) {
            Quiz::MODE_MCQ => $countByType[Question::TYPE_MCQ],
            Quiz::MODE_THEORY => $countByType[Question::TYPE_THEORY],
            Quiz::MODE_MIXED => $countByType[Question::TYPE_MCQ] + $countByType[Question::TYPE_THEORY],
            default => 0,
        };
    }

    public function availableQuestionCountsByMode(array $subjectIds, array $topicIds = [], ?string $difficulty = null): array
    {
        $countByType = $this->countByType($subjectIds, $topicIds, $difficulty);

        return [
            Quiz::MODE_MCQ => $countByType[Question::TYPE_MCQ],
            Quiz::MODE_THEORY => $countByType[Question::TYPE_THEORY],
            Quiz::MODE_MIXED => $countByType[Question::TYPE_MCQ] + $countByType[Question::TYPE_THEORY],
        ];
    }

    public function execute(User $student, array $payload): Quiz
    {
        $subjectIds = collect($payload['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $subjects = Subject::query()->active()->whereIn('id', $subjectIds)->get(['id', 'name', 'level']);

        if ($subjects->isEmpty()) {
            throw new RuntimeException('Select at least one active subject.');
        }

        $levels = collect($payload['levels'] ?? [])
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, Subject::levels(), true))
            ->unique()
            ->values();

        if ($levels->isEmpty()) {
            $levels = collect([$subjects->first()->level]);
        }

        if ($subjects->contains(fn (Subject $subject) => ! $levels->contains($subject->level))) {
            throw new RuntimeException('Selected subjects are outside your chosen level scope.');
        }

        $topicIds = $this->sanitizeTopicIds($subjectIds, $payload['topic_ids'] ?? []);
        $mode = $payload['mode'];
        $questionCount = (int) $payload['question_count'];
        $difficulty = $payload['difficulty'] ?? null;

        $selectedQuestions = $this->selectQuestions(
            subjectIds: $subjectIds,
            topicIds: $topicIds,
            mode: $mode,
            questionCount: $questionCount,
            difficulty: $difficulty
        );

        if ($selectedQuestions->count() < $questionCount) {
            $available = $this->availableQuestionCount($subjectIds, $topicIds, $mode, $difficulty);

            throw new RuntimeException("Only {$available} question(s) are available for this selection.");
        }

        $singleSubjectId = count($subjectIds) === 1 ? $subjectIds[0] : null;

        return DB::transaction(function () use ($student, $levels, $singleSubjectId, $mode, $selectedQuestions): Quiz {
            $quiz = Quiz::query()->create([
                'user_id' => $student->id,
                'level' => $levels->first(),
                'subject_id' => $singleSubjectId,
                'mode' => $mode,
                'status' => Quiz::STATUS_IN_PROGRESS,
                'total_questions' => $selectedQuestions->count(),
                'total_possible_score' => $selectedQuestions->sum(fn (Question $question) => (float) $question->marks),
                'started_at' => now(),
            ]);

            $selectedQuestions
                ->values()
                ->each(function (Question $question, int $index) use ($quiz): void {
                    $quiz->quizQuestions()->create([
                        'question_id' => $question->id,
                        'order_no' => $index + 1,
                        'question_snapshot' => $this->snapshotFor($question),
                        'max_score' => $question->marks,
                        'requires_manual_review' => $question->type === Question::TYPE_THEORY,
                    ]);
                });

            return $quiz;
        });
    }

    private function selectQuestions(array $subjectIds, array $topicIds, string $mode, int $questionCount, ?string $difficulty): Collection
    {
        if ($mode === Quiz::MODE_MCQ || $mode === Quiz::MODE_THEORY) {
            return $this->baseQuestionQuery($subjectIds, $topicIds, $difficulty)
                ->ofType($mode)
                ->inRandomOrder()
                ->limit($questionCount)
                ->get();
        }

        $counts = $this->countByType($subjectIds, $topicIds, $difficulty);

        $targetMcq = (int) floor($questionCount / 2);
        $targetTheory = $questionCount - $targetMcq;

        $mcqTake = min($counts[Question::TYPE_MCQ], $targetMcq);
        $theoryTake = min($counts[Question::TYPE_THEORY], $targetTheory);

        $remaining = $questionCount - ($mcqTake + $theoryTake);

        if ($remaining > 0) {
            $mcqSpare = max(0, $counts[Question::TYPE_MCQ] - $mcqTake);
            $mcqExtra = min($mcqSpare, $remaining);
            $mcqTake += $mcqExtra;
            $remaining -= $mcqExtra;
        }

        if ($remaining > 0) {
            $theorySpare = max(0, $counts[Question::TYPE_THEORY] - $theoryTake);
            $theoryTake += min($theorySpare, $remaining);
        }

        $mcqQuestions = $mcqTake > 0
            ? $this->baseQuestionQuery($subjectIds, $topicIds, $difficulty)
                ->mcq()
                ->inRandomOrder()
                ->limit($mcqTake)
                ->get()
            : collect();

        $theoryQuestions = $theoryTake > 0
            ? $this->baseQuestionQuery($subjectIds, $topicIds, $difficulty)
                ->theory()
                ->inRandomOrder()
                ->limit($theoryTake)
                ->get()
            : collect();

        return $mcqQuestions
            ->merge($theoryQuestions)
            ->shuffle()
            ->values();
    }

    private function baseQuestionQuery(array $subjectIds, array $topicIds, ?string $difficulty): Builder
    {
        $query = Question::query()
            ->availableForStudents()
            ->whereIn('subject_id', $subjectIds)
            ->with([
                'mcqOptions' => fn ($builder) => $builder->orderBy('sort_order')->orderBy('id'),
                'theoryMeta:id,question_id,sample_answer,grading_notes,keywords,acceptable_phrases,max_score',
                'topic:id,name,subject_id',
                'subject:id,name',
            ]);

        if ($topicIds !== []) {
            $query->whereIn('topic_id', $topicIds);
        }

        if ($difficulty !== null && $difficulty !== '') {
            $query->where('difficulty', $difficulty);
        }

        return $query;
    }

    private function countByType(array $subjectIds, array $topicIds, ?string $difficulty): array
    {
        $query = Question::query()
            ->availableForStudents()
            ->whereIn('subject_id', $subjectIds);

        if ($topicIds !== []) {
            $query->whereIn('topic_id', $topicIds);
        }

        if ($difficulty !== null && $difficulty !== '') {
            $query->where('difficulty', $difficulty);
        }

        $rows = $query
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            Question::TYPE_MCQ => (int) ($rows[Question::TYPE_MCQ] ?? 0),
            Question::TYPE_THEORY => (int) ($rows[Question::TYPE_THEORY] ?? 0),
        ];
    }

    private function sanitizeTopicIds(array $subjectIds, array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return DB::table('topics')
            ->where('is_active', true)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('id', $topicIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function snapshotFor(Question $question): array
    {
        $snapshot = [
            'id' => $question->id,
            'subject_id' => $question->subject_id,
            'subject_name' => $question->subject?->name,
            'topic_id' => $question->topic_id,
            'topic_name' => $question->topic?->name,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'question_image_path' => $question->question_image_path,
            'difficulty' => $question->difficulty,
            'marks' => (float) $question->marks,
            'ideal_time_seconds' => $this->idealTimeSecondsFor($question),
            'explanation' => $question->explanation,
        ];

        if ($question->type === Question::TYPE_MCQ) {
            $snapshot['options'] = $question->mcqOptions
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'option_key' => $option->option_key,
                    'option_text' => $option->option_text,
                    'sort_order' => $option->sort_order,
                    'is_correct' => (bool) $option->is_correct,
                ])
                ->values()
                ->all();
        }

        if ($question->type === Question::TYPE_THEORY && $question->theoryMeta) {
            $snapshot['theory_meta'] = [
                'sample_answer' => $question->theoryMeta->sample_answer,
                'grading_notes' => $question->theoryMeta->grading_notes,
                'keywords' => $question->theoryMeta->keywords ?? [],
                'acceptable_phrases' => $question->theoryMeta->acceptable_phrases ?? [],
                'max_score' => (float) $question->theoryMeta->max_score,
            ];
        }

        return $snapshot;
    }

    private function idealTimeSecondsFor(Question $question): int
    {
        $base = $question->type === Question::TYPE_THEORY ? 180 : 60;
        $difficultyBoost = match ($question->difficulty) {
            'hard' => 60,
            'medium' => 30,
            default => 0,
        };

        return $base + $difficultyBoost;
    }
}
