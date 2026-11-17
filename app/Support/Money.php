<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Helpers for USD amounts stored as integer nano-dollars (1 USD = 1e9 nanos).
 *
 * Pixelforge prices are fractions of a cent (e.g. 12.50 USD / 1M tokens =
 * 0.0000125 USD per token) and GET /v1/usage reports 9 decimals, so nanos let
 * us store every amount exactly as an integer, with no float rounding.
 */
final class Money
{
    public const NANOS_PER_USD = 1_000_000_000;

    /** Parse an exact decimal string such as "12.50" or "0.373875" into nanos. */
    public static function fromDecimal(string $usd): int
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,9}))?$/', trim($usd), $m)) {
            throw new InvalidArgumentException("Invalid USD amount [{$usd}].");
        }

        $nanos = (int) $m[2] * self::NANOS_PER_USD + (int) str_pad($m[3] ?? '', 9, '0');

        return $m[1] === '-' ? -$nanos : $nanos;
    }

    public static function fromDollars(int $usd): int
    {
        return $usd * self::NANOS_PER_USD;
    }

    /** Format nanos as an exact decimal string with 9 places, e.g. "0.373875000". */
    public static function toDecimal(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $abs = abs($nanos);

        return sprintf('%s%d.%09d', $sign, intdiv($abs, self::NANOS_PER_USD), $abs % self::NANOS_PER_USD);
    }

    /** Integer division rounding up (for positive amounts), so we never undercharge. */
    public static function divideCeil(int $numerator, int $denominator): int
    {
        return intdiv($numerator + $denominator - 1, $denominator);
    }
}
