<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyQuizUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_payment_id',
        'usage_date',
        'quiz_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }
}
