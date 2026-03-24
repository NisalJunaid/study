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

    public static function normalizeColor(?string $color, string $fallback = '#4f46e5'): string
    {
        if (! is_string($color)) {
            return $fallback;
        }

        $trimmed = trim($color);

        if (! preg_match('/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', $trimmed, $matches)) {
            return $fallback;
        }

        $hex = $matches[1];

        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        return '#'.strtolower($hex);
    }

    public static function colorToRgba(?string $color, float $alpha = 0.14): string
    {
        $hex = self::normalizeColor($color);
        $normalizedAlpha = max(0, min(1, $alpha));

        return sprintf(
            'rgba(%d, %d, %d, %.2f)',
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
            $normalizedAlpha
        );
    }
}
