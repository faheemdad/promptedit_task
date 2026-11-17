<?php

namespace Tests\Unit;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Billing\Markup;
use App\Services\Pricing\PricingCalculator;
use App\Support\Money;
use Tests\TestCase;

class PricingTest extends TestCase
{
    public function test_money_parses_and_formats_exact_decimals(): void
    {
        $this->assertSame(12_500_000_000, Money::fromDecimal('12.50'));
        $this->assertSame(373_875_000, Money::fromDecimal('0.373875000'));
        $this->assertSame('0.373875000', Money::toDecimal(373_875_000));
        $this->assertSame('-1.000000001', Money::toDecimal(-1_000_000_001));
    }

    public function test_provider_cost_matches_the_documented_worked_example(): void
    {
        $this->fakePixelforge();
        $calculator = app(PricingCalculator::class);

        // Docs: high, 1536x1024, 400-char prompt (115 tokens), 2 refs, 8000 reasoning = $0.373875
        $cost = $calculator->providerCost([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ], $this->pricingResponse()['rates']);

        $this->assertSame('0.373875000', Money::toDecimal($cost));
    }

    public function test_estimate_gives_expected_and_worst_case_with_markup(): void
    {
        $this->fakePixelforge();

        $estimate = app(PricingCalculator::class)->estimate(str_repeat('a', 400), ImageSize::Landscape, ImageQuality::High, 2);

        // Worst case: ceil(100 * 1.15) = 115 text tokens, 8000 reasoning -> the docs' $0.373875.
        $this->assertSame(373_875_000, $estimate->providerMax);
        $this->assertSame(467_343_750, $estimate->userMax);
        // Expected: 100 text tokens, (1000 + 8000) / 2 = 4500 reasoning.
        $this->assertSame(100 * 5_000 + 1530 * 10_000 + 6450 * 40_000 + 4500 * 12_500, $estimate->providerExpected);
    }

    public function test_markup_adds_25_percent_and_rounds_up_to_the_nano(): void
    {
        $markup = new Markup(2500);

        $this->assertSame(125, $markup->apply(100));
        $this->assertSame(2, $markup->apply(1)); // 1.25 nanos -> 2, never undercharge
        $this->assertSame(0, $markup->apply(0));
    }
}
