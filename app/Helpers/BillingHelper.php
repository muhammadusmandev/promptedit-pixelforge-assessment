<?php

if (! function_exists('formatNanoDollars')) {
    /**
     * Format integer nanodollars into USD decimal string.
     *
     * @param int $amount Amount in nanodollars.
     * @return string
     */
    function formatNanoDollars(int $amount): string
    {
        return sprintf(
            '%d.%09d',
            intdiv($amount, 1_000_000_000),
            $amount % 1_000_000_000
        );
    }
}

if (! function_exists('toNanoDollars')) {
    /**
     * Convert USD decimal string into integer nanodollars.
     *
     * @param string $amount USD amount.
     * @return int
     */
    function toNanoDollars(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 1_000_000_000)
            + (int) str_pad(substr($fraction, 0, 9), 9, '0');
    }
}