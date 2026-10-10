<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of Kenya's counties and the delivery zone it falls in. A county with no
 * zone, or in a zone that is switched off, is not delivered to.
 */
#[Fillable(['name', 'delivery_zone_id'])]
class County extends Model
{
    /** @return BelongsTo<DeliveryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    /** @param Builder<County> $query */
    public function scopeDeliveredTo(Builder $query): void
    {
        $query->whereHas('zone', fn (Builder $zone) => $zone->where('is_active', true));
    }
}
