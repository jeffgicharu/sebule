@php $staff = request()->is('admin', 'admin/*'); @endphp

<x-shop.plain title="Not yours to open">
    <p class="eyebrow">Not yours to open</p>
    <h1 class="mt-3 text-4xl lg:text-6xl">You do not have access to this page.</h1>
    @if ($staff)
        <p class="mt-5 text-lg text-ink-soft">Your account does not cover this part of the back office. If it should, ask an owner to change your role.</p>
        <p class="mt-8">
            <a href="{{ url('/admin') }}" class="btn btn-primary">Back to the back office</a>
        </p>
    @else
        <p class="mt-5 text-lg text-ink-soft">If you followed a link here, it may have been meant for somebody else.</p>
        <p class="mt-8">
            <a href="{{ route('home') }}" class="btn btn-primary">Go to the home page</a>
        </p>
    @endif
</x-shop.plain>
