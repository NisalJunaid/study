<?php

namespace App\Services\Admin;

use App\Models\McqOption;
use App\Models\Question;
use App\Models\QuizQuestion;
use App\Models\StructuredQuestionPart;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CurriculumDataManagementService
{
    public function stats(): array
    {
        return [
            'subjects' => Subject::withTrashed()->count(),
            'topics' => Topic::withTrashed()->count(),
            'questions' => Question::withTrashed()->count(),
            'mcq_options' => McqOption::count(),
            'theory_meta' => TheoryQuestionMeta::count(),
            'structured_parts' => StructuredQuestionPart::count(),
            'student_answers' => StudentAnswer::count(),
            'quiz_questions' => QuizQuestion::count(),
        ];
    }

    public function wipe(string $scope): array
    {
        return DB::transaction(function () use ($scope): array {
            return match ($scope) {
                'subjects' => $this->wipeSubjects(),
                'topics' => $this->wipeTopics(),
                'questions' => $this->wipeQuestions(),
                'answers' => $this->wipeAnswers(),
                'all' => $this->wipeAll(),
                default => throw new \InvalidArgumentException('Unsupported wipe scope.'),
            };
        });
    }

    public function wipeSubjects(?array $subjectIds = null): array
    {
        $subjectIds = $this->resolvedIds(Subject::class, $subjectIds);

        if ($subjectIds === []) {
            return ['subjects_deleted' => 0, 'topics_deleted' => 0, 'questions_deleted' => 0, 'student_answers_deleted' => 0, 'quiz_questions_deleted' => 0];
        }

        $questionCounts = $this->wipeQuestions($subjectIds, 'subject_id');

        $topicQuery = Topic::withTrashed()->whereIn('subject_id', $subjectIds);
        $topicsDeleted = (clone $topicQuery)->count();
        $topicQuery->forceDelete();

        $subjectQuery = Subject::withTrashed()->whereIn('id', $subjectIds);
        $subjectsDeleted = (clone $subjectQuery)->count();
        $subjectQuery->forceDelete();

        return array_merge($questionCounts, [
            'topics_deleted' => $topicsDeleted,
            'subjects_deleted' => $subjectsDeleted,
        ]);
    }

    public function wipeTopics(?array $topicIds = null): array
    {
        $topicIds = $this->resolvedIds(Topic::class, $topicIds);

        if ($topicIds === []) {
            return ['topics_deleted' => 0, 'questions_deleted' => 0, 'student_answers_deleted' => 0, 'quiz_questions_deleted' => 0];
        }

        $questionCounts = $this->wipeQuestions($topicIds, 'topic_id');

        $topicQuery = Topic::withTrashed()->whereIn('id', $topicIds);
        $topicsDeleted = (clone $topicQuery)->count();
        $topicQuery->forceDelete();

        return array_merge($questionCounts, [
            'topics_deleted' => $topicsDeleted,
        ]);
    }

    public function wipeQuestions(?array $questionIds = null, string $column = 'id'): array
    {
        $questionIds = $this->resolvedIds(Question::class, $questionIds, $column);

        if ($questionIds === []) {
            return ['questions_deleted' => 0, 'student_answers_deleted' => 0, 'quiz_questions_deleted' => 0];
        }

        $studentAnswerQuery = StudentAnswer::query()->whereIn('question_id', $questionIds);
        $studentAnswersDeleted = (clone $studentAnswerQuery)->count();
        $studentAnswerQuery->delete();

        $quizQuestionQuery = QuizQuestion::query()->whereIn('question_id', $questionIds);
        $quizQuestionsDeleted = (clone $quizQuestionQuery)->count();
        $quizQuestionQuery->delete();

        McqOption::query()->whereIn('question_id', $questionIds)->delete();
        TheoryQuestionMeta::query()->whereIn('question_id', $questionIds)->delete();
        StructuredQuestionPart::query()->whereIn('question_id', $questionIds)->delete();

        $questionQuery = Question::withTrashed()->whereIn('id', $questionIds);
        $questionsDeleted = (clone $questionQuery)->count();
        $questionQuery->forceDelete();

        return [
            'questions_deleted' => $questionsDeleted,
            'student_answers_deleted' => $studentAnswersDeleted,
            'quiz_questions_deleted' => $quizQuestionsDeleted,
        ];
    }

    public function wipeAnswers(): array
    {
        $studentAnswersDeleted = StudentAnswer::query()->count();
        $mcqOptionsDeleted = McqOption::query()->count();
        $theoryMetaDeleted = TheoryQuestionMeta::query()->count();
        $structuredPartsDeleted = StructuredQuestionPart::query()->count();

        StudentAnswer::query()->delete();
        McqOption::query()->delete();
        TheoryQuestionMeta::query()->delete();
        StructuredQuestionPart::query()->delete();

        return [
            'student_answers_deleted' => $studentAnswersDeleted,
            'mcq_options_deleted' => $mcqOptionsDeleted,
            'theory_meta_deleted' => $theoryMetaDeleted,
            'structured_parts_deleted' => $structuredPartsDeleted,
        ];
    }

    public function wipeAll(): array
    {
        return array_merge(
            $this->wipeAnswers(),
            $this->wipeQuestions(),
            $this->wipeTopics(),
            $this->wipeSubjects(),
        );
    }

    private function resolvedIds(string $modelClass, ?array $ids = null, string $column = 'id'): array
    {
        if ($ids === null) {
            return $modelClass::withTrashed()->pluck('id')->all();
        }

        return $modelClass::withTrashed()
            ->whereIn($column, Arr::wrap($ids))
            ->pluck('id')
            ->all();
    }
}
