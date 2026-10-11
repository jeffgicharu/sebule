<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A place a customer has deliveries sent, written the way Kenyan addresses
 * work: county, town or area, estate or building, house, and directions.
 */
#[Fillable(['customer_id', 'county_id', 'town', 'estate', 'house', 'directions', 'is_default'])]
class CustomerAddress extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** The address on one line, in the order a rider reads it. */
    public function line(): string
    {
        return implode(', ', array_filter([$this->house, $this->estate, $this->town, $this->county?->name]));
    }
}
