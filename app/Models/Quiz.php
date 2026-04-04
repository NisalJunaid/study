<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Quiz extends Model
{
    use HasFactory;

    public const MODE_MCQ = 'mcq';
    public const MODE_THEORY = 'theory';
    public const MODE_MIXED = 'mixed';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADING = 'grading';
    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'user_id',
        'level',
        'subject_id',
        'mode',
        'status',
        'billing_access_type',
        'subscription_payment_id',
        'total_questions',
        'total_possible_score',
        'total_awarded_score',
        'started_at',
        'last_interacted_at',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'total_possible_score' => 'decimal:2',
        'total_awarded_score' => 'decimal:2',
        'started_at' => 'datetime',
        'last_interacted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function subscriptionPayment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class);
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSubmittedAttempts($query)
    {
        return $query
            ->whereNotNull('submitted_at')
            ->whereIn('status', self::submittedAttemptStatuses());
    }

    public function scopeAbandonable($query, \Carbon\CarbonInterface $cutoff)
    {
        return $query
            ->whereNull('submitted_at')
            ->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS])
            ->whereRaw('COALESCE(last_interacted_at, started_at, updated_at) <= ?', [$cutoff]);
    }

    public function markInteracted(?\Carbon\CarbonInterface $at = null): void
    {
        if (! $this->canAcceptAnswerChanges()) {
            return;
        }

        $this->forceFill([
            'last_interacted_at' => $at ?? now(),
        ])->save();
    }

    public function isSubmittedAttempt(): bool
    {
        return $this->submitted_at !== null && in_array($this->status, self::submittedAttemptStatuses(), true);
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        if ($this->status === $targetStatus) {
            return true;
        }

        return in_array($targetStatus, self::allowedTransitions()[$this->status] ?? [], true);
    }

    public function transitionTo(string $targetStatus, array $attributes = []): bool
    {
        if (! $this->canTransitionTo($targetStatus)) {
            throw new RuntimeException("Invalid quiz state transition [{$this->status} -> {$targetStatus}].");
        }

        if ($this->status !== $targetStatus) {
            $attributes['status'] = $targetStatus;
        }

        if ($attributes === []) {
            return false;
        }

        return $this->forceFill($attributes)->save();
    }

    public function canAcceptAnswerChanges(): bool
    {
        return $this->submitted_at === null
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_IN_PROGRESS, self::STATUS_SUBMITTED],
            self::STATUS_IN_PROGRESS => [self::STATUS_SUBMITTED],
            self::STATUS_SUBMITTED => [self::STATUS_GRADING, self::STATUS_GRADED],
            self::STATUS_GRADING => [self::STATUS_GRADED],
            self::STATUS_GRADED => [],
        ];
    }

    public static function submittedAttemptStatuses(): array
    {
        return [self::STATUS_SUBMITTED, self::STATUS_GRADING, self::STATUS_GRADED];
    }
}
