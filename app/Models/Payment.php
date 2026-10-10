<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for an order. Amounts are in cents.
 */
#[Fillable([
    'order_id', 'method', 'state', 'amount', 'charged', 'phone', 'merchant_request_id', 'checkout_request_id',
    'receipt', 'result_code', 'result_description', 'response', 'checked_at', 'settled_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'state' => PaymentState::class,
            'amount' => 'integer',
            'charged' => 'integer',
            'response' => 'array',
            'checked_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->state === PaymentState::Pending;
    }
}
