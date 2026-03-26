<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'currency',
        'registration_fee',
        'payment_instructions',
    ];

    protected $casts = [
        'registration_fee' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'bank_account_name' => 'Focus Lab Collections',
            'bank_account_number' => '0000000000',
            'bank_name' => 'Your Bank',
            'currency' => 'USD',
            'registration_fee' => 0,
            'payment_instructions' => 'Transfer the exact amount and upload a clear slip for verification.',
        ]);
    }
}
