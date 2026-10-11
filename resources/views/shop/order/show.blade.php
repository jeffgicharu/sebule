@php
    use App\Enums\DeliveryMethod;
    use App\Enums\OrderEventType;
    use App\Enums\OrderStatus;
    use App\Enums\PaymentState;
    use App\Models\DeliveryZone;
    use App\Support\Money;
    use App\Support\Phone;

    $delivering = $order->delivery_method === DeliveryMethod::Delivery;
    $made = $order->items->contains('made_to_order', true);
    $showroom = config('store.showroom');
    $paid = $order->payments->firstWhere('state', PaymentState::Paid);

    // How far along the order is, and when it reached each step.
    $reached = array_search($order->status, [
        OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::InProduction,
        OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered,
    ], true);
    $when = fn (OrderEventType $type) => $order->events->firstWhere('type', $type)?->created_at;

    $steps = array_values(array_filter([
        ['Order placed', 0, $order->created_at],
        ['Payment received', 1, $order->paid_at],
        $made ? ['Being made for you', 2, $when(OrderEventType::InProduction)] : null,
        [$delivering ? 'Ready for delivery' : 'Ready to collect', 3, $when(OrderEventType::Ready)],
        $delivering ? ['Out for delivery', 4, $when(OrderEventType::OutForDelivery)] : null,
        [$delivering ? 'Delivered' : 'Collected', 5, $when(OrderEventType::Delivered)],
    ]));

    $first = Str::of($order->customer_name)->before(' ');
@endphp

<x-shop.layout :title="'Order '.$order->number" description="Your Sebule order and how it is getting on.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <p class="eyebrow">Order {{ $order->number }}</p>

        @if ($order->status === OrderStatus::PendingPayment)
            <h1 class="mt-3 text-4xl lg:text-6xl">One step left, {{ $first }}.</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">Your order is placed and your pieces are held for you, but it is not paid for yet.</p>
            <p class="mt-6"><a href="{{ route('orders.pay', $order) }}" class="btn btn-primary min-h-12">Pay {{ Money::format($order->total) }} now</a></p>
        @elseif ($order->status === OrderStatus::Expired)
            <h1 class="mt-3 text-4xl lg:text-6xl">This order was not paid in time.</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">Nothing was taken from you, and the pieces went back on sale. If they are still in stock you can pick the order up where you left it.</p>
            <p class="mt-6"><a href="{{ route('orders.pay', $order) }}" class="btn btn-primary min-h-12">Pick the order up again</a></p>
        @elseif ($order->status === OrderStatus::Cancelled)
            <h1 class="mt-3 text-4xl lg:text-6xl">This order was cancelled.</h1>
        @elseif ($order->status === OrderStatus::Delivered)
            <h1 class="mt-3 text-4xl lg:text-6xl">{{ $delivering ? 'Delivered' : 'Collected' }}. Enjoy it, {{ $first }}.</h1>
        @else
            <h1 class="mt-3 text-4xl lg:text-6xl">Thank you, {{ $first }}.</h1>
            <p class="mt-4 max-w-xl text-lg text-ink-soft">
                We have your payment and your order is confirmed.
                @if ($delivering)
                    We will call <span class="whitespace-nowrap">{{ Phone::display($order->phone) }}</span> to arrange delivery.
                @else
                    We will text <span class="whitespace-nowrap">{{ Phone::display($order->phone) }}</span> when it is ready to collect.
                @endif
            </p>
            <p class="mt-3 max-w-xl text-[0.95rem] text-ink-soft">Keep the address of this page: it always shows where your order has got to.</p>
        @endif

        <div class="mt-12 grid gap-12 lg:grid-cols-12">
            <div class="space-y-10 lg:col-span-7">
                @if ($reached !== false)
                    <section aria-labelledby="order-progress">
                        <h2 id="order-progress" class="text-2xl">Where it has got to</h2>

                        <ol class="mt-6">
                            @foreach ($steps as [$label, $rank, $time])
                                @php $done = $reached >= $rank; $current = $reached === $rank; @endphp
                                <li class="relative flex gap-4 pb-7 last:pb-0" @if ($current) aria-current="step" @endif>
                                    @unless ($loop->last)
                                        <span aria-hidden="true" class="absolute left-[0.6875rem] top-6 h-full w-px {{ $reached > $rank ? 'bg-forest' : 'bg-ink/20' }}"></span>
                                    @endunless
                                    <span aria-hidden="true" class="relative mt-0.5 grid size-[1.4375rem] shrink-0 place-items-center rounded-full {{ $done ? 'bg-forest text-paper' : 'border border-ink/35 bg-paper' }}">
                                        @if ($done)
                                            <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m3.5 8.5 3 3 6-7" /></svg>
                                        @endif
                                    </span>
                                    <div>
                                        <p class="{{ $done ? 'font-semibold' : 'text-ink-soft' }}">{{ $label }}<span class="sr-only">{{ $done ? ', done' : ', still to come' }}</span></p>
                                        @if ($done && $time)
                                            <p class="text-sm text-ink-soft">{{ $time->format('j F Y, g:i a') }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <section aria-labelledby="order-delivery" class="border-t border-rule pt-8">
                    <h2 id="order-delivery" class="text-2xl">{{ $delivering ? 'Delivery' : 'Collection' }}</h2>

                    <p class="mt-4 font-medium">{{ $order->customer_name }} <span aria-hidden="true">&middot;</span> <span class="whitespace-nowrap">{{ Phone::display($order->phone) }}</span></p>

                    @if ($delivering)
                        <p class="mt-1">{{ $order->addressLine() }}</p>
                        @if ($order->directions)
                            <p class="mt-1 text-ink-soft">{{ $order->directions }}</p>
                        @endif
                        <p class="mt-3 text-[0.95rem] text-ink-soft">Delivered in {{ DeliveryZone::describeDays($order->delivery_days_min, $order->delivery_days_max) }} from payment.@if ($made) Pieces that are made to order follow when they are ready.@endif</p>
                    @else
                        <p class="mt-1">Sebule showroom, {{ $showroom['street'] }}, {{ $showroom['city'] }}</p>
                        <p class="mt-1 text-ink-soft">Monday to Saturday, 9 am to 6 pm</p>
                    @endif
                </section>
            </div>

            <aside class="lg:col-span-5" aria-labelledby="order-summary">
                <div class="bg-tile p-6 lg:p-8">
                    <h2 id="order-summary" class="text-2xl">What you ordered</h2>
                    <div class="mt-3"><x-shop.order-lines :lines="$order->items->map->summary()" /></div>
                    <div class="border-t border-rule pt-4"><x-shop.order-totals :order="$order" /></div>

                    @if ($paid)
                        <p class="mt-5 border-t border-rule pt-4 text-[0.95rem]">
                            Paid by {{ $paid->method->inSentence() }} on {{ $paid->settled_at->format('j F Y') }}@if ($paid->receipt), receipt {{ $paid->receipt }}@endif.
                        </p>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-shop.layout>
