<?php

namespace App\Services\Pricing;

use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the current Pixelforge price sheet. It is cached and refreshed in
 * the background, so a short Pixelforge outage never blocks estimates.
 *
 * Only estimates use this. Actual costs always use the rates Pixelforge
 * fixed on the generation itself when it was created.
 */
class PricingCatalog
{
    public function __construct(private readonly PixelforgeClient $client) {}

    public function current(): PriceSheet
    {
        $fresh = config('pixelforge.pricing_cache_seconds');

        $pricing = Cache::flexible('pixelforge.pricing', [$fresh, $fresh * 24], fn () => $this->client->pricing());

        return PriceSheet::fromApi($pricing);
    }
}
