<?php

namespace App\Support;

/**
 * Amounts are held as whole numbers of cents and never as floats.
 */
class Money
{
    /** "KSh 45,000", or "KSh 45,000.50" when there are cents. */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        $shillings = intdiv($cents, 100);
        $remainder = $cents % 100;

        $formatted = number_format($shillings);

        if ($remainder !== 0) {
            $formatted .= '.'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);
        }

        return $sign.'KSh '.$formatted;
    }

    /** Whole shillings to cents. */
    public static function fromShillings(int $shillings): int
    {
        return $shillings * 100;
    }

    /**
     * Parse what a person typed, such as "45,000" or "45000.50", into cents.
     */
    public static function parse(string|int|float|null $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $clean = str_replace([',', ' ', 'KSh', 'Ksh', 'KES'], '', (string) $amount);

        if (! is_numeric($clean)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '0');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
