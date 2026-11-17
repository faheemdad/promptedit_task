<?php

namespace App\Services\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Billing\Markup;
use App\Support\Money;

class PricingCalculator
{
    private const TOKENS_PER_RATE_UNIT = 1_000_000;

    public function __construct(
        private readonly PricingCatalog $catalog,
        private readonly Markup $markup,
    ) {}

    public function estimate(string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImageCount): CostEstimate
    {
        $sheet = $this->catalog->current();

        $textTokens = (int) ceil(mb_strlen($prompt) / $sheet->charactersPerTextToken());
        $reasoning = $sheet->reasoningTokens($quality);

        $known = [
            'input_image_tokens' => $referenceImageCount * $sheet->tokensPerReferenceImage(),
            'output_tokens' => $sheet->outputTokens($size, $quality),
        ];

        $expected = $this->providerCost([
            ...$known,
            'input_text_tokens' => $textTokens,
            'reasoning_tokens' => intdiv($reasoning['min'] + $reasoning['max'], 2),
        ], $sheet->rates);

        $max = $this->providerCost([
            ...$known,
            'input_text_tokens' => (int) ceil($textTokens * (1 + $sheet->textTokenVariance())),
            'reasoning_tokens' => $reasoning['max'],
        ], $sheet->rates);

        return new CostEstimate(
            providerExpected: $expected,
            providerMax: $max,
            userExpected: $this->markup->apply($expected),
            userMax: $this->markup->apply($max),
        );
    }

    /**
     * Exact provider cost in nano-USD: sum of tokens x rate / 1M, per token type.
     *
     * @param  array<string, int>  $usage  Token counts keyed like the rates.
     * @param  array<string, string>  $rates  USD per 1M tokens, as given by Pixelforge.
     */
    public function providerCost(array $usage, array $rates): int
    {
        $total = 0;

        foreach ($rates as $tokenType => $rate) {
            $total += (int) ($usage[$tokenType] ?? 0) * Money::fromDecimal($rate);
        }

        // With Pixelforge's rates this division is always exact; rounding only
        // matters if a rate ever has more than 3 decimals.
        return intdiv($total + intdiv(self::TOKENS_PER_RATE_UNIT, 2), self::TOKENS_PER_RATE_UNIT);
    }
}
