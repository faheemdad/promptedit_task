<?php

namespace App\Services\Billing;

use App\Support\Money;

/** Turns a provider cost into the price we charge the user. */
class Markup
{
    private const BPS_DENOMINATOR = 10_000;

    public function __construct(private readonly int $basisPoints) {}

    /** Provider cost plus markup, rounded up to the next nano-dollar. */
    public function apply(int $providerCostNanos): int
    {
        return Money::divideCeil(
            $providerCostNanos * (self::BPS_DENOMINATOR + $this->basisPoints),
            self::BPS_DENOMINATOR,
        );
    }
}
