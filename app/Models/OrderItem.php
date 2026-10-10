<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order: the piece as it was named and priced on the day.
 */
#[Fillable([
    'order_id', 'product_variant_id', 'product_name', 'variant_label', 'sku', 'unit_price', 'quantity',
    'line_total', 'made_to_order', 'lead_time_weeks', 'stock_taken',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'made_to_order' => 'boolean',
            'lead_time_weeks' => 'integer',
            'stock_taken' => 'boolean',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The variant as it is today, if it is still in the catalogue.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * The line as a summary shows it.
     *
     * @return array{photo: mixed, name: string, label: string|null, quantity: int, price: int, total: int, note: string|null}
     */
    public function summary(): array
    {
        return [
            'photo' => $this->variant?->photo(),
            'name' => $this->product_name,
            'label' => $this->variant_label,
            'quantity' => $this->quantity,
            'price' => $this->unit_price,
            'total' => $this->line_total,
            'note' => $this->made_to_order ? 'Made to order in about '.$this->lead_time_weeks.' weeks' : null,
        ];
    }

    /** "Amani 3-seater sofa, Forest" */
    public function title(): string
    {
        return $this->product_name.($this->variant_label ? ', '.$this->variant_label : '');
    }
}
