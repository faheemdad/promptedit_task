<?php

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\InsufficientCreditsException;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Records a generation, reserves its worst-case price, and queues it for
 * submission to Pixelforge. The HTTP request never waits on Pixelforge, so
 * rate limits and overloads become a short wait, not an error.
 */
class CreateGeneration
{
    public function __construct(
        private readonly PricingCalculator $pricing,
        private readonly CreditLedger $ledger,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     *
     * @throws InsufficientCreditsException
     */
    public function handle(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $estimate = $this->pricing->estimate($prompt, $size, $quality, count($referenceImages));

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $estimate) {
            $generation = $user->generations()->create([
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'estimated_cost' => $estimate->userExpected,
                'max_cost' => $estimate->userMax,
            ]);

            $this->ledger->hold($generation);

            return $generation;
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
