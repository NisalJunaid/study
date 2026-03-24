<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TheoryQuestionMeta extends Model
{
    use HasFactory;

    protected $table = 'theory_question_meta';

    protected $fillable = [
        'question_id',
        'sample_answer',
        'grading_notes',
        'keywords',
        'acceptable_phrases',
        'max_score',
    ];

    protected $casts = [
        'keywords' => 'array',
        'acceptable_phrases' => 'array',
        'max_score' => 'decimal:2',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
