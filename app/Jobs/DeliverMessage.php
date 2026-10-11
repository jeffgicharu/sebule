<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Mail\CustomerEmail;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends an email that is waiting to go, and writes down whether it went.
 */
class DeliverMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before the second and third tries. */
    public array $backoff = [30, 180];

    public function __construct(public Message $message) {}

    public function handle(): void
    {
        if ($this->message->status !== MessageStatus::Waiting) {
            return;
        }

        Mail::to($this->message->recipient)->send(new CustomerEmail($this->message));

        $this->message->update(['status' => MessageStatus::Sent, 'sent_at' => now(), 'error' => null]);
    }

    public function failed(?Throwable $problem): void
    {
        $this->message->update([
            'status' => MessageStatus::Failed,
            'error' => mb_substr($problem?->getMessage() ?? 'The email service did not accept it.', 0, 250),
        ]);
    }
}
