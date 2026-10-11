<x-shop.layout title="Open an account" description="Keep your addresses and orders in one place.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <div class="mx-auto max-w-xl">
            <p class="eyebrow">Your account</p>
            <h1 class="mt-3 text-4xl lg:text-5xl">Open an account</h1>
            <p class="mt-3 text-ink-soft">You never need one to buy from us. An account keeps your addresses for next time, shows your orders in one place and carries your cart from your phone to your laptop.</p>

            <form method="post" action="{{ route('account.register.store') }}" novalidate class="mt-8 space-y-5">
                @csrf
                @if (request('next') === 'checkout' || old('next') === 'checkout')
                    <input type="hidden" name="next" value="checkout">
                @endif

                <x-shop.errors />

                <x-shop.field name="name" label="Full name" required autocomplete="name" />
                <x-shop.field name="email" label="Email" type="email" required autocomplete="email" hint="You sign in with this, and it is where we send order updates." />
                <x-shop.field name="phone" label="Mobile number" type="tel" autocomplete="tel" inputmode="tel" placeholder="0712 345 678" hint="Fills in checkout for you." />
                <x-shop.field name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 8 characters." />

                <label class="flex cursor-pointer items-start gap-3 pt-1 text-[0.95rem]">
                    <input type="hidden" name="marketing" value="0">
                    <input type="checkbox" name="marketing" value="1" class="mt-1 size-4 accent-forest" @checked(old('marketing'))>
                    <span>Send me news of new pieces and offers now and then. <span class="text-ink-soft">You can stop these at any time.</span></span>
                </label>

                <p class="pt-2"><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Open my account</button></p>
            </form>

            <p class="mt-8 border-t border-rule pt-6 text-[0.95rem]">Already have an account? <a href="{{ route('account.login', request()->only('next')) }}" class="link font-semibold">Sign in</a></p>
        </div>
    </div>
</x-shop.layout>
