<?php

namespace App\Services\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * Pixelforge's published pricing (GET /v1/pricing): rates per 1M tokens and
 * the token counts each size/quality produces.
 */
final class PriceSheet
{
    /**
     * @param  array<string, string>  $rates  USD per 1M tokens, keyed by token type.
     * @param  array<string, mixed>  $tokens  The "tokens" block of the pricing response.
     */
    public function __construct(
        public readonly array $rates,
        private readonly array $tokens,
    ) {}

    /** @param array<string, mixed> $pricing */
    public static function fromApi(array $pricing): self
    {
        return new self($pricing['rates'], $pricing['tokens']);
    }

    public function charactersPerTextToken(): int
    {
        return (int) $this->tokens['characters_per_text_token'];
    }

    public function textTokenVariance(): float
    {
        return (float) $this->tokens['text_token_variance'];
    }

    public function tokensPerReferenceImage(): int
    {
        return (int) $this->tokens['per_reference_image'];
    }

    public function outputTokens(ImageSize $size, ImageQuality $quality): int
    {
        return (int) $this->tokens['output'][$quality->value][$size->value];
    }

    /** @return array{min: int, max: int} */
    public function reasoningTokens(ImageQuality $quality): array
    {
        $range = $this->tokens['reasoning'][$quality->value];

        return ['min' => (int) $range['min'], 'max' => (int) $range['max']];
    }
}
