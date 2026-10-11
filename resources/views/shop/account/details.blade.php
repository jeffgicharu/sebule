@php use App\Support\Phone; @endphp

<x-shop.account title="Your details" :customer="$customer">
    <div class="max-w-xl space-y-14">
        <form method="post" action="{{ route('account.details.update') }}" novalidate class="space-y-5">
            @csrf
            @method('put')

            <x-shop.errors />

            <x-shop.field name="name" label="Full name" :value="$customer->name" required autocomplete="name" />
            <x-shop.field name="email" label="Email" type="email" :value="$customer->email" required autocomplete="email" hint="You sign in with this, and it is where we send order updates." />
            <x-shop.field name="phone" label="Mobile number" type="tel" :value="$customer->phone ? Phone::display($customer->phone) : null" autocomplete="tel" inputmode="tel" placeholder="0712 345 678" hint="Fills in checkout for you." />

            <label class="flex cursor-pointer items-start gap-3 pt-1 text-[0.95rem]">
                <input type="hidden" name="marketing" value="0">
                <input type="checkbox" name="marketing" value="1" class="mt-1 size-4 accent-forest" @checked(old('marketing', $customer->marketing_opt_in))>
                <span>Send me news of new pieces and offers now and then. <span class="text-ink-soft">Untick this to stop them. Messages about your orders are sent either way.</span></span>
            </label>

            <p class="pt-2"><button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Save my details</button></p>
        </form>

        <section aria-labelledby="change-password" class="border-t border-rule pt-10">
            <h2 id="change-password" class="text-2xl">Change your password</h2>
            <p class="mt-2 text-[0.95rem] text-ink-soft">Changing it signs your account out on every other device.</p>

            <form method="post" action="{{ route('account.password.change') }}" novalidate class="mt-6 space-y-5">
                @csrf
                @method('put')

                <x-shop.errors bag="password" />

                {{-- These two boxes report to their own list of mistakes, so the form above is not blamed for them. --}}
                @php $passwordErrors = $errors->getBag('password'); @endphp
                @foreach ([['current_password', 'Current password', 'current-password', null], ['password', 'New password', 'new-password', 'At least 8 characters.']] as [$name, $label, $autocomplete, $hint])
                    <div>
                        <label for="{{ $name }}" class="block text-[0.95rem] font-semibold">{{ $label }}</label>
                        @if ($hint)
                            <p id="{{ $name }}-hint" class="mt-1 text-sm text-ink-soft">{{ $hint }}</p>
                        @endif
                        <input id="{{ $name }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}"
                            class="mt-2 min-h-12 w-full border bg-paper px-3.5 text-base {{ $passwordErrors->has($name) ? 'border-clay-deep ring-1 ring-clay-deep' : 'border-ink/45' }}"
                            @if ($passwordErrors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @elseif ($hint) aria-describedby="{{ $name }}-hint" @endif>
                        @if ($passwordErrors->has($name))
                            <p id="{{ $name }}-error" class="mt-1.5 text-sm font-medium text-clay-deep">{{ $passwordErrors->first($name) }}</p>
                        @endif
                    </div>
                @endforeach

                <p class="pt-2"><button class="btn btn-quiet min-h-12 w-full sm:w-auto sm:px-10">Change my password</button></p>
            </form>
        </section>
    </div>
</x-shop.account>
