{{-- What an order came to. --}}
@props(['order'])

@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;

    $pieces = $order->items->sum('quantity');
@endphp

<dl class="space-y-3 text-[0.95rem]">
    <div class="flex justify-between gap-6">
        <dt>{{ $pieces }} {{ Str::plural('piece', $pieces) }}</dt>
        <dd>{{ Money::format($order->subtotal) }}</dd>
    </div>
    <div class="flex justify-between gap-6">
        <dt>{{ $order->delivery_method === DeliveryMethod::Delivery ? 'Delivery to '.$order->county : 'Collection from the showroom' }}</dt>
        <dd>{{ $order->delivery_method === DeliveryMethod::Delivery ? Money::format($order->delivery_fee) : 'Free' }}</dd>
    </div>
    <div class="flex items-baseline justify-between gap-6 border-t border-rule pt-4">
        <dt class="font-semibold">Total</dt>
        <dd class="shrink-0 whitespace-nowrap font-display text-3xl">{{ Money::format($order->total) }}</dd>
    </div>
</dl>

<p class="mt-2 text-sm text-ink-soft">Includes {{ Money::format($order->vat_amount) }} VAT at {{ rtrim(rtrim(number_format($order->vat_rate / 100, 2), '0'), '.') }}%</p>
