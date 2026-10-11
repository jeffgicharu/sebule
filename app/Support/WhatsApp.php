<?php

namespace App\Support;

/**
 * Links that open a WhatsApp chat with the store, with the first message
 * already written. There is no link when the store has set no WhatsApp number.
 */
class WhatsApp
{
    public static function link(?string $message = null): ?string
    {
        $number = preg_replace('/\D+/', '', (string) config('store.whatsapp'));

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.($message ? '?text='.rawurlencode($message) : '');
    }
}
