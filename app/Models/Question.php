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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'is_published' => 'boolean',
    ];

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
}
