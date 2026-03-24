<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_READY = 'ready';

    public const STATUS_IMPORTING = 'importing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    protected $fillable = [
        'uploaded_by',
        'file_name',
        'file_path',
        'status',
        'allow_create_subjects',
        'allow_create_topics',
        'total_rows',
        'valid_rows',
        'imported_rows',
        'failed_rows',
        'error_summary',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'allow_create_subjects' => 'boolean',
        'allow_create_topics' => 'boolean',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
