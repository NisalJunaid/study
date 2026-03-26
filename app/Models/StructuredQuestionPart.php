<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StructuredQuestionPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'part_label',
        'prompt_text',
        'max_score',
        'sample_answer',
        'marking_notes',
        'sort_order',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
