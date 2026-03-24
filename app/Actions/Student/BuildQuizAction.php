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
    public function availableQuestionCount(Subject $subject, array $topicIds, string $mode, ?string $difficulty): int
    {
        $countByType = $this->countByType($subject, $topicIds, $difficulty);

        return match ($mode) {
            Quiz::MODE_MCQ => $countByType[Question::TYPE_MCQ],
            Quiz::MODE_THEORY => $countByType[Question::TYPE_THEORY],
            Quiz::MODE_MIXED => $countByType[Question::TYPE_MCQ] + $countByType[Question::TYPE_THEORY],
            default => 0,
        };
    }

    public function execute(User $student, array $payload): Quiz
    {
        $subject = Subject::query()->active()->findOrFail($payload['subject_id']);
        $topicIds = $this->sanitizeTopicIds($subject, $payload['topic_ids'] ?? []);
        $mode = $payload['mode'];
        $questionCount = (int) $payload['question_count'];
        $difficulty = $payload['difficulty'] ?? null;

        $selectedQuestions = $this->selectQuestions(
            subject: $subject,
            topicIds: $topicIds,
            mode: $mode,
            questionCount: $questionCount,
            difficulty: $difficulty
        );

        if ($selectedQuestions->count() < $questionCount) {
            $available = $this->availableQuestionCount($subject, $topicIds, $mode, $difficulty);

            throw new RuntimeException("Only {$available} question(s) are available for this selection.");
        }

        return DB::transaction(function () use ($student, $subject, $mode, $selectedQuestions): Quiz {
            $quiz = Quiz::query()->create([
                'user_id' => $student->id,
                'subject_id' => $subject->id,
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

    private function selectQuestions(Subject $subject, array $topicIds, string $mode, int $questionCount, ?string $difficulty): Collection
    {
        if ($mode === Quiz::MODE_MCQ || $mode === Quiz::MODE_THEORY) {
            return $this->baseQuestionQuery($subject, $topicIds, $difficulty)
                ->ofType($mode)
                ->inRandomOrder()
                ->limit($questionCount)
                ->get();
        }

        $counts = $this->countByType($subject, $topicIds, $difficulty);

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
            ? $this->baseQuestionQuery($subject, $topicIds, $difficulty)
                ->mcq()
                ->inRandomOrder()
                ->limit($mcqTake)
                ->get()
            : collect();

        $theoryQuestions = $theoryTake > 0
            ? $this->baseQuestionQuery($subject, $topicIds, $difficulty)
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

    private function baseQuestionQuery(Subject $subject, array $topicIds, ?string $difficulty): Builder
    {
        $query = Question::query()
            ->availableForStudents()
            ->where('subject_id', $subject->id)
            ->with([
                'mcqOptions' => fn ($builder) => $builder->orderBy('sort_order')->orderBy('id'),
                'theoryMeta:id,question_id,sample_answer,grading_notes,keywords,acceptable_phrases,max_score',
                'topic:id,name,subject_id',
            ]);

        if ($topicIds !== []) {
            $query->whereIn('topic_id', $topicIds);
        }

        if ($difficulty !== null && $difficulty !== '') {
            $query->where('difficulty', $difficulty);
        }

        return $query;
    }

    private function countByType(Subject $subject, array $topicIds, ?string $difficulty): array
    {
        $rows = $this->baseQuestionQuery($subject, $topicIds, $difficulty)
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return [
            Question::TYPE_MCQ => (int) ($rows[Question::TYPE_MCQ] ?? 0),
            Question::TYPE_THEORY => (int) ($rows[Question::TYPE_THEORY] ?? 0),
        ];
    }

    private function sanitizeTopicIds(Subject $subject, array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return $subject->topics()
            ->active()
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
            'topic_id' => $question->topic_id,
            'topic_name' => $question->topic?->name,
            'type' => $question->type,
            'question_text' => $question->question_text,
            'question_image_path' => $question->question_image_path,
            'difficulty' => $question->difficulty,
            'marks' => (float) $question->marks,
            'explanation' => $question->explanation,
        ];

        if ($question->type === Question::TYPE_MCQ) {
            $snapshot['options'] = $question->mcqOptions
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'option_key' => $option->option_key,
                    'option_text' => $option->option_text,
                    'sort_order' => $option->sort_order,
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
}
