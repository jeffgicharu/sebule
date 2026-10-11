<x-shop.layout title="Sign in" description="Sign in to your Sebule account.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <div class="mx-auto max-w-xl">
            <p class="eyebrow">Your account</p>
            <h1 class="mt-3 text-4xl lg:text-5xl">Sign in</h1>
            <p class="mt-3 text-ink-soft">Looking for an order you placed without an account? The link in its confirmation still opens it.</p>

            <form method="post" action="{{ route('account.login.store') }}" novalidate class="mt-8 space-y-5">
                @csrf
                @if (request('next') === 'checkout' || old('next') === 'checkout')
                    <input type="hidden" name="next" value="checkout">
                @endif

                <x-shop.errors />

                <x-shop.field name="email" label="Email" type="email" required autocomplete="email" />
                <x-shop.field name="password" label="Password" type="password" required autocomplete="current-password" />

                <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 text-[0.95rem]">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" name="remember" value="1" class="size-4 accent-forest" @checked(old('remember'))>
                        <span>Keep me signed in on this device</span>
                    </label>
                    <a href="{{ route('account.password.request') }}" class="link">Forgotten your password?</a>
                </div>

                <p class="pt-2"><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Sign in</button></p>
            </form>

            <p class="mt-8 border-t border-rule pt-6 text-[0.95rem]">New here? <a href="{{ route('account.register', request()->only('next')) }}" class="link font-semibold">Open an account</a></p>
        </div>
    </div>
</x-shop.layout>
