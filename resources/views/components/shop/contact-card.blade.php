{{-- The ways to reach the store. Each is shown only if the store has set it. --}}
@props(['about' => null])

@php
    use App\Messages\OrderWording;
    use App\Support\Phone;
    use App\Support\WhatsApp;

    $showroom = config('store.showroom');
    $phone = Phone::normalise((string) config('store.phone'));
    $chat = WhatsApp::link($about ?? 'Hello Sebule, I have a question.');
    $email = config('store.email.reply_to');
    $map = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($showroom['street'].', '.$showroom['city']);
@endphp

<div {{ $attributes->class('bg-tile p-6 lg:p-8') }}>
    <h2 class="text-2xl">Reach us</h2>

    <dl class="mt-5 space-y-5 text-[0.95rem]">
        @if ($phone)
            <div>
                <dt class="eyebrow">Call</dt>
                <dd class="mt-1.5"><a href="tel:+{{ $phone }}" class="link text-lg font-semibold">{{ Phone::display($phone) }}</a></dd>
            </div>
        @endif
        @if ($chat)
            <div>
                <dt class="eyebrow">WhatsApp</dt>
                <dd class="mt-2"><a href="{{ $chat }}" rel="noopener" class="btn btn-primary">Chat with us on WhatsApp</a></dd>
            </div>
        @endif
        @if ($email)
            <div>
                <dt class="eyebrow">Email</dt>
                <dd class="mt-1.5"><a href="mailto:{{ $email }}" class="link break-words font-semibold">{{ $email }}</a></dd>
            </div>
        @endif
        <div>
            <dt class="eyebrow">Showroom</dt>
            <dd class="mt-1.5">
                <span class="block font-semibold">{{ $showroom['street'] }}, {{ $showroom['city'] }}</span>
                <span class="block text-ink-soft">{{ OrderWording::hours() }}</span>
                <a href="{{ $map }}" rel="noopener" class="link mt-1.5 inline-block">Find it on a map</a>
            </dd>
        </div>
    </dl>
</div>
