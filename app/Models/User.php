<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STUDENT = 'student';

    public const ONBOARDING_FREE_TRIAL = 'free_trial';
    public const ONBOARDING_SUBSCRIBE = 'subscribe';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'onboarding_intent',
        'id_document_number',
        'nationality',
        'contact_number',
        'id_document_path',
        'id_document_original_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function dailyQuizUsages(): HasMany
    {
        return $this->hasMany(DailyQuizUsage::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)->latestOfMany();
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'uploaded_by');
    }

    public function createdQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'created_by');
    }

    public function updatedQuestions(): HasMany
    {
        return $this->hasMany(Question::class, 'updated_by');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeStudents($query)
    {
        return $query->where('role', self::ROLE_STUDENT);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasTrialRemaining(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        if ($this->onboarding_intent === self::ONBOARDING_SUBSCRIBE) {
            return false;
        }

        return $this->quizzes()->count() === 0;
    }

    public function hasTemporaryAccess(): bool
    {
        $latestPendingPayment = $this->payments()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->latest('submitted_at')
            ->first();

        return $latestPendingPayment?->temporaryAccessStillValid() ?? false;
    }

    public function temporaryQuizQuotaRemaining(): int
    {
        $payment = $this->payments()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->latest('submitted_at')
            ->first();

        if (! $payment || ! $payment->temporaryAccessStillValid()) {
            return 0;
        }

        $used = (int) $this->dailyQuizUsages()
            ->where('subscription_payment_id', $payment->id)
            ->whereDate('usage_date', now()->toDateString())
            ->value('quiz_count');

        return max(0, $payment->temporary_quiz_limit - $used);
    }

    public function currentBillingState(): string
    {
        $subscription = $this->currentSubscription()->first();

        if (! $subscription) {
            return UserSubscription::BILLING_INACTIVE;
        }

        return (string) $subscription->billing_status;
    }
}
