<?php

namespace App\Jobs;

use App\Actions\Generations\GenerationLifecycle;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Sends a generation to Pixelforge. On rate limits, concurrency limits,
 * overloads and network errors it waits and tries again with the same
 * Idempotency-Key, so a retry can never create or bill a second image.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Unlimited releases, bounded by retryUntil(). */
    public int $tries = 0;

    /** Unexpected exceptions (bugs) still stop the job quickly. */
    public int $maxExceptions = 3;

    public function __construct(public Generation $generation) {}

    public function retryUntil(): Carbon
    {
        return $this->generation->created_at->copy()->addMinutes(config('pixelforge.submit_give_up_minutes'));
    }

    public function handle(PixelforgeClient $client, GenerationLifecycle $lifecycle): void
    {
        $generation = $this->generation->fresh();

        if ($generation->status !== GenerationStatus::Pending) {
            return;
        }

        $generation->increment('submit_attempts');
        $generation->update(['submitted_at' => $generation->submitted_at ?? now()]);

        try {
            $remote = $client->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ], idempotencyKey: $generation->id);
        } catch (PixelforgeException $e) {
            if ($e->isTransient()) {
                $this->release(($e->retryAfter ?? $this->backoffSeconds()) + random_int(0, 2));

                return;
            }

            $lifecycle->failBeforeSubmission($generation, $e->errorCode, $e->getMessage());

            return;
        }

        $lifecycle->applyRemote($remote);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationLifecycle::class)->failBeforeSubmission(
            $this->generation,
            'submission_timeout',
            'Pixelforge was too busy to accept this generation. You were not charged.',
        );
    }

    private function backoffSeconds(): int
    {
        return min(60, 2 ** min($this->attempts(), 6));
    }
}
