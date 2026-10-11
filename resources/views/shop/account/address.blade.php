@php $editing = $address->exists; @endphp

<x-shop.account :title="$editing ? 'Change an address' : 'Add an address'" :customer="$customer">
    <form method="post" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}" novalidate class="max-w-xl space-y-5">
        @csrf
        @if ($editing)
            @method('put')
        @endif

        <x-shop.errors />

        <div>
            <label for="county_id" class="block text-[0.95rem] font-semibold">County</label>
            <select id="county_id" name="county_id" required
                class="mt-2 min-h-12 w-full border bg-paper px-3 {{ $errors->has('county_id') ? 'border-clay-deep ring-1 ring-clay-deep' : 'border-ink/45' }}"
                @error('county_id') aria-invalid="true" aria-describedby="county_id-error" @enderror>
                <option value="">Choose a county</option>
                @foreach ($counties as $county)
                    <option value="{{ $county->id }}" @selected((int) old('county_id', $address->county_id) === $county->id)>{{ $county->name }}</option>
                @endforeach
            </select>
            @error('county_id')
                <p id="county_id-error" class="mt-1.5 text-sm font-medium text-clay-deep">{{ $message }}</p>
            @enderror
        </div>

        <x-shop.field name="town" label="Town or area" :value="$address->town" required autocomplete="address-level2" placeholder="Kilimani" />
        <x-shop.field name="estate" label="Estate, building or street" :value="$address->estate" required autocomplete="address-line1" placeholder="Rose Avenue, Maple Court" />
        <x-shop.field name="house" label="House or flat number" :value="$address->house" autocomplete="address-line2" placeholder="B4" />
        <x-shop.field name="directions" label="Directions for the driver" :value="$address->directions" multiline
            hint="A landmark, the gate colour, or anything that helps us find you." />

        @unless ($editing && $address->is_default)
            <label class="flex cursor-pointer items-start gap-3 text-[0.95rem]">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" class="mt-1 size-4 accent-forest" @checked(old('is_default'))>
                <span>Make this my usual address</span>
            </label>
        @endunless

        <p class="flex flex-wrap items-center gap-x-6 gap-y-3 pt-2">
            <button class="btn btn-primary min-h-12 w-full sm:w-auto sm:px-10">Save the address</button>
            <a href="{{ route('account.addresses.index') }}" class="link">Cancel</a>
        </p>
    </form>
</x-shop.account>
