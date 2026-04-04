<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_MCQ = 'mcq';
    public const TYPE_THEORY = 'theory';
    public const TYPE_STRUCTURED_RESPONSE = 'structured_response';
    public const FLAG_DUPLICATE_SUSPECTED = 'duplicate_suspected';
    public const FLAG_MISSING_EXPLANATION = 'missing_explanation';
    public const FLAG_INVALID_OPTIONS_ANSWER_MISMATCH = 'invalid_options_answer_mismatch';
    public const FLAG_NEEDS_REVIEW_AFTER_IMPORT = 'needs_review_after_import';

    protected $fillable = [
        'subject_id',
        'topic_id',
        'type',
        'question_text',
        'question_image_path',
        'difficulty',
        'explanation',
        'marks',
        'is_published',
        'moderation_flags',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'is_published' => 'boolean',
        'moderation_flags' => 'array',
    ];

    public static function theoryLikeTypes(): array
    {
        return [self::TYPE_THEORY, self::TYPE_STRUCTURED_RESPONSE];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function mcqOptions(): HasMany
    {
        return $this->hasMany(McqOption::class);
    }

    public function theoryMeta(): HasOne
    {
        return $this->hasOne(TheoryQuestionMeta::class);
    }

    public function structuredParts(): HasMany
    {
        return $this->hasMany(StructuredQuestionPart::class)->orderBy('sort_order')->orderBy('id');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeMcq($query)
    {
        return $query->where('type', self::TYPE_MCQ);
    }

    public function scopeTheory($query)
    {
        return $query->where('type', self::TYPE_THEORY);
    }

    public function scopeTheoryLike($query)
    {
        return $query->whereIn('type', self::theoryLikeTypes());
    }

    public function scopeAvailableForStudents($query)
    {
        return $query
            ->published()
            ->whereHas('subject', fn ($subjectQuery) => $subjectQuery->active())
            ->where(function ($builder): void {
                $builder
                    ->whereNull('topic_id')
                    ->orWhereHas('topic', fn ($topicQuery) => $topicQuery->active());
            });
    }

    public function scopeWithModerationFlag($query, string $flag)
    {
        return $query->whereJsonContains('moderation_flags', $flag);
    }

    public function moderationFlags(): array
    {
        return collect($this->moderation_flags ?? [])
            ->filter(fn ($flag) => is_string($flag) && trim($flag) !== '')
            ->map(fn (string $flag) => trim($flag))
            ->unique()
            ->values()
            ->all();
    }

    public function hasModerationFlag(string $flag): bool
    {
        return in_array($flag, $this->moderationFlags(), true);
    }

    public function syncModerationFlags(array $flags): void
    {
        $this->forceFill([
            'moderation_flags' => collect($flags)
                ->filter(fn ($flag) => is_string($flag) && trim($flag) !== '')
                ->map(fn (string $flag) => trim($flag))
                ->unique()
                ->values()
                ->all(),
        ])->save();
    }

    public function dismissModerationFlag(string $flag): void
    {
        $this->syncModerationFlags(
            collect($this->moderationFlags())
                ->reject(fn (string $item) => $item === $flag)
                ->values()
                ->all()
        );
    }

    public function publishReadinessIssues(): array
    {
        $issues = [];

        if ($this->type === self::TYPE_MCQ) {
            $options = $this->relationLoaded('mcqOptions') ? $this->mcqOptions : $this->mcqOptions()->get();
            $nonEmptyOptions = $options->filter(fn ($option) => trim((string) $option->option_text) !== '');
            $correctCount = $options->where('is_correct', true)->count();

            if ($nonEmptyOptions->count() < 2) {
                $issues[] = 'MCQ questions must include at least two non-empty options before publishing.';
            }

            if ($correctCount !== 1) {
                $issues[] = 'MCQ questions must have exactly one correct option before publishing.';
            }
        }

        if ($this->type === self::TYPE_THEORY) {
            $theoryMeta = $this->relationLoaded('theoryMeta') ? $this->theoryMeta : $this->theoryMeta()->first();

            if (! $theoryMeta || trim((string) $theoryMeta->sample_answer) === '') {
                $issues[] = 'Theory questions require a sample answer before publishing.';
            }

            if ((float) $this->marks <= 0) {
                $issues[] = 'Theory questions must have marks greater than 0 before publishing.';
            }
        }

        if ($this->type === self::TYPE_STRUCTURED_RESPONSE) {
            $parts = $this->relationLoaded('structuredParts') ? $this->structuredParts : $this->structuredParts()->get();
            $totalPartMarks = $parts->sum(fn ($part) => (float) $part->max_score);

            if ($parts->count() < 1) {
                $issues[] = 'Structured response questions require at least one part before publishing.';
            }

            if ($totalPartMarks <= 0) {
                $issues[] = 'Structured response questions need a scoring basis with part marks greater than 0 before publishing.';
            }
        }

        return $issues;
    }
}
