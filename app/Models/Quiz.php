<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'total_questions',
        'total_possible_score',
        'total_awarded_score',
        'started_at',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'total_possible_score' => 'decimal:2',
        'total_awarded_score' => 'decimal:2',
        'started_at' => 'datetime',
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
}
