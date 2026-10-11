<x-shop.layout title="Choose a new password" description="Choose a new password for your Sebule account.">
    <x-slot:head>
        <meta name="robots" content="noindex">
        {{-- The address of this page is the key to the account, so it is not passed on to any other site. --}}
        <meta name="referrer" content="no-referrer">
    </x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <div class="mx-auto max-w-xl">
            <p class="eyebrow">Your account</p>
            <h1 class="mt-3 text-4xl lg:text-5xl">Choose a new password</h1>

            <form method="post" action="{{ route('account.password.update') }}" novalidate class="mt-8 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <x-shop.field name="email" label="Email" type="email" :value="$email" required autocomplete="email" />
                <x-shop.field name="password" label="New password" type="password" required autocomplete="new-password" hint="At least 8 characters." />

                <p class="pt-2"><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Change my password</button></p>
            </form>

            <p class="mt-8 border-t border-rule pt-6 text-[0.95rem]"><a href="{{ route('account.password.request') }}" class="link font-semibold">Ask for a new link</a></p>
        </div>
    </div>
</x-shop.layout>
