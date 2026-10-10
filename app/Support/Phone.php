<?php

namespace App\Support;

/**
 * Kenyan mobile numbers. Customers type them as 0712 345 678, 0110 123 456,
 * +254 712 345 678 or 254712345678; the store keeps one form, 254712345678,
 * which is also the form M-Pesa expects.
 */
class Phone
{
    /** The number in the stored form, or null when it is not a Kenyan mobile number. */
    public static function normalise(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if (str_starts_with($digits, '254')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Mobile numbers are nine digits after the country code and start with 7 or 1.
        return preg_match('/^[71]\d{8}$/', $digits) === 1 ? '254'.$digits : null;
    }

    /** The stored form as people read it: 0712 345 678. */
    public static function display(?string $stored): string
    {
        $local = '0'.substr((string) $stored, 3);

        return trim(substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7));
    }
}
