<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    public const LEVEL_O = 'o_level';
    public const LEVEL_A = 'a_level';

    protected $fillable = [
        'name',
        'slug',
        'level',
        'description',
        'color',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function levels(): array
    {
        return [
            self::LEVEL_O,
            self::LEVEL_A,
        ];
    }

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            self::LEVEL_A => "A'Level",
            self::LEVEL_O => "O'Level",
            default => ucfirst(str_replace('_', ' ', $level)),
        };
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}
