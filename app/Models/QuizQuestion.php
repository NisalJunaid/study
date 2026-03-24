<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_id',
        'order_no',
        'question_snapshot',
        'max_score',
        'awarded_score',
        'is_correct',
        'requires_manual_review',
    ];

    protected $casts = [
        'question_snapshot' => 'array',
        'max_score' => 'decimal:2',
        'awarded_score' => 'decimal:2',
        'is_correct' => 'boolean',
        'requires_manual_review' => 'boolean',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function studentAnswer(): HasOne
    {
        return $this->hasOne(StudentAnswer::class);
    }

    public function scopeNeedsManualReview($query)
    {
        return $query->where('requires_manual_review', true);
    }
}
