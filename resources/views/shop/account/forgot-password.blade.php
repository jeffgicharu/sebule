<x-shop.layout title="Forgotten your password?" description="Choose a new password for your Sebule account.">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <div class="wrap pt-8 lg:pt-12">
        <div class="mx-auto max-w-xl">
            <p class="eyebrow">Your account</p>
            <h1 class="mt-3 text-4xl lg:text-5xl">Forgotten your password?</h1>
            <p class="mt-3 text-ink-soft">Tell us the email address of your account and we will send it a link to choose a new one.</p>

            <form method="post" action="{{ route('account.password.email') }}" novalidate class="mt-8 space-y-5">
                @csrf
                <x-shop.field name="email" label="Email" type="email" required autocomplete="email" />
                <p class="pt-2"><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Send me the link</button></p>
            </form>

            <p class="mt-8 border-t border-rule pt-6 text-[0.95rem]"><a href="{{ route('account.login') }}" class="link font-semibold">Back to sign in</a></p>
        </div>
    </div>
</x-shop.layout>
