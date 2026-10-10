<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of counties delivered to at one fee. The fee is in cents.
 */
#[Fillable(['name', 'fee', 'days_min', 'days_max', 'is_active', 'position'])]
class DeliveryZone extends Model
{
    protected function casts(): array
    {
        return [
            'fee' => 'integer',
            'days_min' => 'integer',
            'days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<County, $this> */
    public function counties(): HasMany
    {
        return $this->hasMany(County::class)->orderBy('name');
    }

    /** @param Builder<DeliveryZone> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** "2 to 4 working days", or "3 working days" when there is no range. */
    public function estimate(): string
    {
        return self::describeDays($this->days_min, $this->days_max);
    }

    public static function describeDays(int $min, int $max): string
    {
        return $min === $max
            ? $min.' working '.($min === 1 ? 'day' : 'days')
            : $min.' to '.$max.' working days';
    }
}
