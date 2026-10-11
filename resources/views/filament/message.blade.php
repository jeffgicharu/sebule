@php use App\Enums\MessageChannel; @endphp

@if ($message->channel === MessageChannel::Email && $message->html)
    {{-- The email exactly as it was written, shown in a frame so its own styles cannot reach this page. --}}
    <iframe title="The email as the customer sees it" sandbox srcdoc="{{ $message->html }}"
        class="h-[36rem] w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"></iframe>
@else
    <p class="whitespace-pre-line rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">{{ $message->body }}</p>
@endif

@if ($message->error)
    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $message->error }}</p>
@endif
