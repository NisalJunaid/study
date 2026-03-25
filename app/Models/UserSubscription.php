<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    use HasFactory;

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    public const BILLING_ACTIVE = 'active';
    public const BILLING_INACTIVE = 'inactive';
    public const BILLING_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'billing_status',
        'started_at',
        'activated_at',
        'expires_at',
        'grace_ends_at',
        'suspended_at',
        'suspended_reason',
        'verified_at',
        'verified_by',
        'payment_reference',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->billing_status === self::BILLING_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED || $this->billing_status === self::BILLING_SUSPENDED;
    }
}
