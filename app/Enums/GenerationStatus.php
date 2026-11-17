<?php

namespace App\Enums;

enum GenerationStatus: string
{
    /** Saved locally, waiting to be accepted by Pixelforge (e.g. concurrency limit). */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Position in the lifecycle. Statuses only ever move forward, which is what
     * makes duplicate and out-of-order webhooks harmless.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }

    public function isTerminal(): bool
    {
        return $this->rank() === 3;
    }

    public function canTransitionTo(self $next): bool
    {
        return $next->rank() > $this->rank();
    }

    /** @return list<self> */
    public static function inFlight(): array
    {
        return [self::Pending, self::Queued, self::Processing];
    }
}
