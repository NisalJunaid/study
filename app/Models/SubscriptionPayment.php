<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPayment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'user_subscription_id',
        'amount',
        'currency',
        'discount_id',
        'discount_snapshot',
        'payment_method',
        'status',
        'slip_path',
        'slip_original_name',
        'paid_at',
        'submitted_at',
        'verified_at',
        'verified_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'temporary_access_expires_at',
        'temporary_quiz_limit',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount_snapshot' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'temporary_access_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(PlanDiscount::class, 'discount_id');
    }

    public function quizUsages(): HasMany
    {
        return $this->hasMany(DailyQuizUsage::class);
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function temporaryAccessStillValid(): bool
    {
        return $this->isPendingVerification() && $this->temporary_access_expires_at && now()->lte($this->temporary_access_expires_at);
    }
}
