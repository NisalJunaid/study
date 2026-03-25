<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_ANNUAL = 'annual';

    protected $fillable = [
        'code',
        'name',
        'type',
        'price',
        'currency',
        'billing_cycle_days',
        'is_active',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function discounts(): HasMany
    {
        return $this->hasMany(PlanDiscount::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
