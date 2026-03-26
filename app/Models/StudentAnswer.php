<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_GRADED = 'graded';
    public const STATUS_MANUAL_REVIEW = 'manual_review';
    public const STATUS_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'quiz_question_id',
        'question_id',
        'user_id',
        'selected_option_id',
        'answer_text',
        'answer_json',
        'is_correct',
        'score',
        'feedback',
        'grading_status',
        'ai_result_json',
        'question_started_at',
        'answered_at',
        'ideal_time_seconds',
        'answer_duration_seconds',
        'answered_on_time',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
        'answer_json' => 'array',
        'ai_result_json' => 'array',
        'question_started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ideal_time_seconds' => 'integer',
        'answer_duration_seconds' => 'integer',
        'answered_on_time' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function quizQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(McqOption::class, 'selected_option_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('grading_status', $status);
    }
}
