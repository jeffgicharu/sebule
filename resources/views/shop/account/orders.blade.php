@php
    use App\Enums\OrderStatus;
    use App\Support\Money;
@endphp

<x-shop.account title="Your orders" :customer="$customer">
    @if ($orders->isEmpty())
        <div class="border border-rule p-6 lg:p-8">
            <h2 class="text-2xl">No orders yet</h2>
            <p class="mt-2 max-w-lg text-ink-soft">Orders you place while signed in are listed here. An order you placed before opening your account still opens from the link in its confirmation.</p>
            <p class="mt-5"><a href="{{ route('home') }}" class="btn btn-primary">Start shopping</a></p>
        </div>
    @else
        <ul class="divide-y divide-rule border-y border-rule">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('orders.show', $order) }}" class="group flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-5">
                        <span class="min-w-0">
                            <span class="block font-display text-xl group-hover:underline group-hover:decoration-sand group-hover:underline-offset-4">Order {{ $order->number }}</span>
                            <span class="mt-1 block text-[0.95rem] text-ink-soft">{{ $order->created_at->format('j F Y') }} <span aria-hidden="true">&middot;</span> {{ (int) $order->pieces }} {{ Str::plural('piece', (int) $order->pieces) }}</span>
                        </span>
                        <span class="flex items-center gap-5">
                            <span class="text-[0.95rem] {{ $order->status === OrderStatus::PendingPayment ? 'font-semibold text-clay-deep' : 'text-ink-soft' }}">{{ $order->status === OrderStatus::Delivered && $order->delivery_method->value === 'collection' ? 'Collected' : $order->status->getLabel() }}</span>
                            <span class="whitespace-nowrap font-semibold">{{ Money::format($order->total) }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-8">{{ $orders->links() }}</div>
    @endif
</x-shop.account>
