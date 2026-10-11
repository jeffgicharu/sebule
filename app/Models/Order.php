<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's order, with its own copy of the customer, the delivery and the
 * prices as they were when it was placed. Amounts are in cents.
 */
#[Fillable([
    'number', 'token', 'checkout_key', 'status', 'customer_name', 'phone', 'email', 'marketing_opt_in',
    'delivery_method', 'delivery_zone_id', 'zone_name', 'county', 'town', 'estate', 'house', 'directions',
    'delivery_days_min', 'delivery_days_max', 'subtotal', 'delivery_fee', 'total', 'vat_rate', 'vat_amount',
    'held_until', 'paid_at', 'closed_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'marketing_opt_in' => 'boolean',
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'vat_rate' => 'integer',
            'vat_amount' => 'integer',
            'held_until' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** Customers reach an order by its secret token, never by its id or number. */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderByDesc('id');
    }

    /** @return BelongsTo<DeliveryZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function awaitsPayment(): bool
    {
        return $this->status === OrderStatus::PendingPayment;
    }

    /** A prompt the customer has not yet answered. Only one is open at a time. */
    public function openPayment(): ?Payment
    {
        return $this->payments->firstWhere('state', PaymentState::Pending);
    }

    /**
     * The step staff take next. It depends on what the order holds and how it
     * reaches the customer: only made-to-order pieces go through the workshop,
     * and an order that is collected never goes out for delivery.
     */
    public function nextStatus(): ?OrderStatus
    {
        return match ($this->status) {
            OrderStatus::Paid => $this->items->contains('made_to_order', true) ? OrderStatus::InProduction : OrderStatus::Ready,
            OrderStatus::InProduction => OrderStatus::Ready,
            OrderStatus::Ready => $this->delivery_method === DeliveryMethod::Delivery ? OrderStatus::OutForDelivery : OrderStatus::Delivered,
            OrderStatus::OutForDelivery => OrderStatus::Delivered,
            default => null,
        };
    }

    /** An order can be cancelled until it has reached the customer or been closed. */
    public function canBeCancelled(): bool
    {
        return ! $this->status->isClosed();
    }

    /**
     * Orders with money still to send back: a refund is noted as due and no
     * refund has been recorded since.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeOwedARefund(Builder $query): void
    {
        $query->whereHas('events', fn (Builder $due) => $due
            ->where('type', OrderEventType::RefundDue)
            ->whereNotExists(fn ($sent) => $sent
                ->from('order_events as sent')
                ->whereColumn('sent.order_id', 'order_events.order_id')
                ->where('sent.type', OrderEventType::RefundSent->value)
                ->whereColumn('sent.id', '>', 'order_events.id')));
    }

    /** What the history says is owed back, when a refund is still due. */
    public function refundDue(): ?OrderEvent
    {
        $due = $this->events->where('type', OrderEventType::RefundDue)->last();
        $sent = $this->events->where('type', OrderEventType::RefundSent)->last();

        return $due !== null && ($sent === null || $sent->id < $due->id) ? $due : null;
    }

    public function record(OrderEventType $type, ?string $note = null, ?User $by = null): OrderEvent
    {
        return $this->events()->create([
            'type' => $type,
            'note' => $note,
            'user_id' => $by?->getKey(),
        ]);
    }

    /** The address on one line, in the order a rider reads it. */
    public function addressLine(): string
    {
        return implode(', ', array_filter([$this->house, $this->estate, $this->town, $this->county]));
    }
}
