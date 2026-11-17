<?php

namespace App\Services\Pricing;

/**
 * Up-front price of a generation. Output and reference image tokens are
 * exact, while text and reasoning tokens are only known after completion.
 * So we give an expected price and a worst case, which is what we hold.
 * All amounts are nano-USD.
 */
final class CostEstimate
{
    public function __construct(
        public readonly int $providerExpected,
        public readonly int $providerMax,
        public readonly int $userExpected,
        public readonly int $userMax,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'expected' => $this->userExpected,
            'max' => $this->userMax,
        ];
    }
}
