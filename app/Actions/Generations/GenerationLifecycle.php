<?php

namespace App\Actions\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\Markup;
use App\Services\Pricing\PricingCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves generations through their lifecycle and settles their money.
 *
 * Every source of truth (the create response, webhooks, polling) goes
 * through applyRemote(). It locks the row, so concurrent deliveries
 * serialise. A status only ever moves forward, so stale, duplicate and
 * out-of-order updates do nothing.
 */
class GenerationLifecycle
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly PricingCalculator $pricing,
        private readonly Markup $markup,
    ) {}

    /**
     * Apply a Pixelforge generation object to our local record.
     *
     * @param  array<string, mixed>  $remote
     */
    public function applyRemote(array $remote): ?Generation
    {
        return DB::transaction(function () use ($remote) {
            $generation = $this->findLocked($remote);

            if (! $generation) {
                return null;
            }

            $next = GenerationStatus::from($remote['status']);

            if ($this->shouldApply($generation, $next, $remote)) {
                $this->transition($generation, $next, $remote);
            } elseif ($next === GenerationStatus::Succeeded && $generation->status === GenerationStatus::Succeeded) {
                // Same terminal state again (e.g. a poll): only refresh the image link.
                $generation->update([
                    'image_url' => $remote['image_url'] ?? $generation->image_url,
                    'image_url_expires_at' => $this->time($remote['image_url_expires_at'] ?? null) ?? $generation->image_url_expires_at,
                ]);
            }

            return $generation;
        });
    }

    /** Fail a generation Pixelforge never accepted (rejected, or we gave up retrying). */
    public function failBeforeSubmission(Generation $generation, string $errorCode, string $message): void
    {
        DB::transaction(function () use ($generation, $errorCode, $message) {
            $generation = Generation::query()->lockForUpdate()->find($generation->id);

            // Once Pixelforge has accepted it, only Pixelforge decides how it ends.
            if (! $generation || $generation->status !== GenerationStatus::Pending) {
                return;
            }

            $generation->update([
                'status' => GenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $message,
                'completed_at' => now(),
            ]);

            $this->ledger->releaseHold($generation);
        });
    }

    /** @param array<string, mixed> $remote */
    private function shouldApply(Generation $generation, GenerationStatus $next, array $remote): bool
    {
        if ($generation->status->canTransitionTo($next)) {
            return true;
        }

        // We gave up on a submission that Pixelforge did accept after all (e.g.
        // a timed-out request). Its real outcome wins, because Pixelforge bills it.
        return $generation->status === GenerationStatus::Failed
            && $generation->provider_id === null
            && $next->isTerminal()
            && isset($remote['id']);
    }

    /** @param array<string, mixed> $remote */
    private function transition(Generation $generation, GenerationStatus $next, array $remote): void
    {
        $generation->fill([
            'status' => $next,
            'provider_id' => $remote['id'],
            'rates' => $remote['rates'] ?? $generation->rates,
            'started_at' => $this->time($remote['started_at'] ?? null) ?? $generation->started_at,
        ]);

        if ($next === GenerationStatus::Succeeded) {
            $providerCost = $this->pricing->providerCost($remote['usage'], $generation->rates);

            $generation->fill([
                'usage' => $remote['usage'],
                'provider_cost' => $providerCost,
                'charged_cost' => $this->markup->apply($providerCost),
                'image_url' => $remote['image_url'],
                'image_url_expires_at' => $this->time($remote['image_url_expires_at'] ?? null),
                'error_code' => null,
                'error_message' => null,
                'completed_at' => $this->time($remote['completed_at'] ?? null) ?? now(),
            ])->save();

            $this->ledger->settle($generation, $generation->charged_cost);

            return;
        }

        if ($next === GenerationStatus::Failed) {
            $generation->fill([
                'error_code' => $remote['error']['code'] ?? 'generation_failed',
                'error_message' => $remote['error']['message'] ?? null,
                'completed_at' => $this->time($remote['completed_at'] ?? null) ?? now(),
            ])->save();

            $this->ledger->releaseHold($generation);

            return;
        }

        $generation->save();
    }

    /**
     * Find our record for a Pixelforge generation. Our own id is sent as
     * metadata, so this works even when a webhook beats the POST response.
     *
     * @param  array<string, mixed>  $remote
     */
    private function findLocked(array $remote): ?Generation
    {
        $localId = $remote['metadata']['generation_id'] ?? null;

        return Generation::query()
            ->lockForUpdate()
            ->when(
                $localId,
                fn ($q) => $q->whereKey($localId),
                fn ($q) => $q->where('provider_id', $remote['id'] ?? null),
            )
            ->first();
    }

    private function time(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->utc() : null;
    }
}
