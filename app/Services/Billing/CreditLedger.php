<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place that moves credits. Every change is an append-only
 * credit_transactions row plus an atomic update of users.credit_balance,
 * done in one DB transaction.
 *
 * Lifecycle of a generation's money:
 *   submit    -> hold(-max_cost)
 *   succeeded -> hold_release(+max_cost), charge(-actual)
 *   failed    -> hold_release(+max_cost)
 * The unique (generation_id, type) index makes each step happen at most once.
 */
class CreditLedger
{
    public function purchase(User $user, int $amount): CreditTransaction
    {
        return DB::transaction(fn () => $this->record($user->id, null, CreditTransactionType::Purchase, $amount, 'Credit purchase'));
    }

    /**
     * Reserve the worst-case price so the user can never spend more than
     * they have. Call inside the transaction that creates the generation.
     *
     * @throws InsufficientCreditsException
     */
    public function hold(Generation $generation): void
    {
        DB::transaction(function () use ($generation) {
            $balance = (int) User::query()->whereKey($generation->user_id)->lockForUpdate()->value('credit_balance');

            if ($balance < $generation->max_cost) {
                throw new InsufficientCreditsException($generation->max_cost, $balance);
            }

            $this->record($generation->user_id, $generation->id, CreditTransactionType::Hold, -$generation->max_cost, 'Reserved for generation');
        });
    }

    /** Charge the actual price of a succeeded generation and give back its hold. */
    public function settle(Generation $generation, int $charge): void
    {
        DB::transaction(function () use ($generation, $charge) {
            $this->releaseHold($generation);
            $this->recordOnce($generation, CreditTransactionType::Charge, -$charge, 'Generation charge');
        });
    }

    /** Give back the hold of a generation that failed (failed generations are free). */
    public function releaseHold(Generation $generation): void
    {
        $hold = CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->where('type', CreditTransactionType::Hold)
            ->value('amount');

        if ($hold !== null) {
            $this->recordOnce($generation, CreditTransactionType::HoldRelease, -$hold, 'Reservation released');
        }
    }

    private function recordOnce(Generation $generation, CreditTransactionType $type, int $amount, string $description): void
    {
        $exists = CreditTransaction::query()
            ->where('generation_id', $generation->id)
            ->where('type', $type)
            ->exists();

        if (! $exists) {
            $this->record($generation->user_id, $generation->id, $type, $amount, $description);
        }
    }

    private function record(int $userId, ?string $generationId, CreditTransactionType $type, int $amount, string $description): CreditTransaction
    {
        $transaction = CreditTransaction::create([
            'user_id' => $userId,
            'generation_id' => $generationId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
        ]);

        User::query()->whereKey($userId)->increment('credit_balance', $amount);

        return $transaction;
    }
}
