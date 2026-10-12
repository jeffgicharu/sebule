@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentState;
    use App\Payments\Mpesa\MpesaResult;
    use App\Support\Money;
    use App\Support\Phone;

    $expired = $order->status === OrderStatus::Expired;
    $failed = ! $waiting && $last && $last->method === PaymentMethod::Mpesa && $last->state !== PaymentState::Paid ? $last : null;
    $token = $charge !== $order->total;
@endphp

<x-shop.layout :title="'Pay for order '.$order->number" description="Pay for your Sebule order.">
    <x-slot:head>
        <meta name="robots" content="noindex">
        @if ($waiting)
            {{-- Without script the page looks again by itself every few seconds. --}}
            <noscript><meta http-equiv="refresh" content="6"></noscript>
        @endif
    </x-slot:head>

    <div class="wrap pt-6 lg:pt-8">
        <x-shop.steps :current="3" />

        <h1 class="mt-6 text-4xl lg:text-5xl">Pay for your order</h1>
        <p class="mt-3 text-ink-soft">Order {{ $order->number }} for {{ $order->customer_name }}</p>

        <div class="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-7">
                @if ($expired)
                    <section class="border border-rule p-6 lg:p-8">
                        <h2 class="text-2xl">This order ran out of time</h2>
                        <p class="mt-3 text-ink-soft">We held your pieces for {{ config('store.hold_minutes') }} minutes and then put them back on sale. Nothing was paid. If they are still in stock you can pick the order up where you left it.</p>
                        <form method="post" action="{{ route('orders.resume', $order) }}" class="mt-6" data-once>
                            @csrf
                            <button class="btn btn-primary min-h-12">Hold my pieces again</button>
                        </form>
                    </section>
                @elseif ($waiting)
                    {{-- The page asks the store every few seconds whether the payment has landed, and moves on when it has. --}}
                    <section class="border border-forest/40 bg-forest/5 p-6 lg:p-8" data-payment-watch="{{ route('orders.payment', $order) }}" role="status">
                        <div class="flex items-center gap-4">
                            <span aria-hidden="true" class="size-8 shrink-0 animate-spin rounded-full border-[3px] border-forest/25 border-t-forest motion-reduce:animate-none"></span>
                            <h2 class="text-2xl">Check your phone</h2>
                        </div>
                        <p class="mt-4 text-lg">We have sent an M-Pesa prompt to <strong class="whitespace-nowrap font-semibold">{{ Phone::display($waiting->phone) }}</strong>. Enter your M-Pesa PIN there to pay {{ Money::format($waiting->charged) }}.</p>
                        <p class="mt-3 text-ink-soft">Keep this page open. It moves on by itself as soon as the payment arrives.</p>

                        <form method="post" action="{{ route('orders.pay.mpesa', $order) }}" class="mt-6 border-t border-forest/20 pt-5 text-[0.95rem]">
                            @csrf
                            <input type="hidden" name="phone" value="{{ $waiting->phone }}">
                            <p>Nothing on your phone after a minute? <button class="link font-semibold">Send the prompt again</button></p>
                        </form>
                    </section>
                @else
                    @if ($failed)
                        <div role="alert" class="mb-6 border border-clay-deep bg-clay/10 p-5">
                            <p class="font-semibold text-clay-deep">The payment did not go through</p>
                            <p class="mt-1">{{ MpesaResult::message($failed->result_code) }}</p>
                        </div>
                    @endif

                    @if ($mpesa)
                        <section class="border border-rule p-6 lg:p-8">
                            <h2 class="text-2xl">Pay with M-Pesa</h2>
                            <p class="mt-3 text-ink-soft">We send a prompt to your phone. You enter your M-Pesa PIN there, and this page moves on once the payment arrives.</p>

                            <form method="post" action="{{ route('orders.pay.mpesa', $order) }}" class="mt-6 space-y-5" data-once>
                                @csrf
                                <x-shop.field name="phone" label="Safaricom number to send the prompt to" type="tel" :value="$phone" required
                                    autocomplete="tel" inputmode="tel" placeholder="0712 345 678" />

                                @if ($token)
                                    <p class="border-l-2 border-sand pl-4 text-[0.95rem]">The prompt asks for {{ Money::format($charge) }}, not the full total: payments on this site run on Safaricom's test system, and no goods are delivered.</p>
                                @endif

                                <button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Send the prompt to my phone</button>
                            </form>
                        </section>
                    @endif

                    @if (config('store.test_payments'))
                        <section class="{{ $mpesa ? 'mt-6' : '' }} border border-rule p-6 lg:p-8">
                            <h2 class="text-xl">{{ $mpesa ? 'No Safaricom line to hand?' : 'Complete your order' }}</h2>
                            <p class="mt-2 text-ink-soft">You can finish the order with a test payment. No money moves, and no goods are delivered.</p>
                            <form method="post" action="{{ route('orders.pay.test', $order) }}" class="mt-5" data-once>
                                @csrf
                                <button class="btn btn-quiet min-h-12">Complete with a test payment</button>
                            </form>
                        </section>
                    @endif
                @endif

                @if ($order->awaitsPayment() && $order->held_until)
                    <p class="mt-5 text-[0.95rem] text-ink-soft">Your pieces are held for you until {{ $order->held_until->format('g:i a') }}.</p>
                @endif
            </div>

            <aside class="lg:col-span-5" aria-labelledby="pay-summary">
                <div class="bg-tile p-6 lg:sticky lg:top-8 lg:p-8">
                    <h2 id="pay-summary" class="text-2xl">Order {{ $order->number }}</h2>
                    <div class="mt-3"><x-shop.order-lines :lines="$order->items->map->summary()" /></div>
                    <div class="border-t border-rule pt-4"><x-shop.order-totals :order="$order" /></div>
                </div>
            </aside>
        </div>
    </div>
</x-shop.layout>
