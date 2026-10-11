{{-- A line above a form saying how many things need another look. --}}
@props(['bag' => 'default'])

@if ($errors->getBag($bag)->any())
    @php $count = $errors->getBag($bag)->count(); @endphp
    <div role="alert" class="border border-clay-deep bg-clay/10 p-4">
        <p class="font-semibold text-clay-deep">Please check {{ $count === 1 ? 'one thing' : $count.' things' }} below.</p>
    </div>
@endif
