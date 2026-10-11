@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;
    use App\Support\Phone;

    $delivering = $order->delivery_method === DeliveryMethod::Delivery;
    $showroom = config('store.showroom');
@endphp
{!! $wording->heading !!}

@foreach ($wording->lines as $line)
{!! $line !!}

@endforeach
{!! $wording->action !!}: {!! $wording->url !!}

ORDER {!! $order->number !!}
@foreach ($order->items as $item)
{!! $item->quantity !!} x {!! $item->title() !!}: {!! Money::format($item->line_total) !!}
@endforeach
{!! $delivering ? 'Delivery to '.$order->county.': '.Money::format($order->delivery_fee) : 'Collection from the showroom: free' !!}
Total: {!! Money::format($order->total) !!} (includes {!! Money::format($order->vat_amount) !!} VAT)

{!! $delivering ? 'DELIVERY' : 'COLLECTION' !!}
{!! $order->customer_name !!}, {!! Phone::display($order->phone) !!}
@if ($delivering)
{!! $order->addressLine() !!}
@else
Sebule showroom, {!! $showroom['street'] !!}, {!! $showroom['city'] !!}. {!! \App\Messages\OrderWording::hours() !!}
@endif

Sebule, {!! $showroom['street'] !!}, {!! $showroom['city'] !!}
