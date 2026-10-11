<x-shop.account title="Your addresses" :customer="$customer" intro="Where we deliver to. At checkout you start from the one marked as your usual address, and can pick any of the others.">
    @if ($addresses->isEmpty())
        <div class="border border-rule p-6 lg:p-8">
            <h2 class="text-2xl">No addresses saved</h2>
            <p class="mt-2 max-w-lg text-ink-soft">Save an address and checkout fills it in for you.</p>
            <p class="mt-5"><a href="{{ route('account.addresses.create') }}" class="btn btn-primary">Add an address</a></p>
        </div>
    @else
        <ul class="grid gap-5 sm:grid-cols-2">
            @foreach ($addresses as $address)
                <li class="flex flex-col border p-5 {{ $address->is_default ? 'border-ink' : 'border-rule' }}">
                    @if ($address->is_default)
                        <p class="eyebrow !text-forest">Your usual address</p>
                    @endif
                    <p class="mt-2 font-semibold">{{ implode(', ', array_filter([$address->house, $address->estate])) }}</p>
                    <p>{{ $address->town }}, {{ $address->county->name }}</p>
                    @if ($address->directions)
                        <p class="mt-1 text-[0.95rem] text-ink-soft">{{ $address->directions }}</p>
                    @endif
                    @unless ($address->county->zone?->is_active)
                        <p class="mt-2 text-sm font-medium text-clay-deep">We are not delivering to {{ $address->county->name }} at the moment.</p>
                    @endunless

                    <div class="mt-auto flex flex-wrap items-center gap-x-5 gap-y-1 pt-4 text-[0.95rem]">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="link py-1">Change</a>
                        @unless ($address->is_default)
                            <form method="post" action="{{ route('account.addresses.default', $address) }}">
                                @csrf
                                <button class="link py-1">Make this my usual address</button>
                            </form>
                        @endunless
                        <form method="post" action="{{ route('account.addresses.destroy', $address) }}">
                            @csrf
                            @method('delete')
                            <button class="py-1 text-clay-deep underline decoration-clay/40 underline-offset-4 hover:decoration-clay-deep" aria-label="Remove the address {{ $address->line() }}">Remove</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="mt-8"><a href="{{ route('account.addresses.create') }}" class="btn btn-quiet">Add another address</a></p>
    @endif
</x-shop.account>
