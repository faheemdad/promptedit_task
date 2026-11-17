<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger row. The sum of a user's rows is their balance.
 *
 * @property int $user_id
 * @property string|null $generation_id
 * @property CreditTransactionType $type
 * @property int $amount Signed nano-USD.
 * @property string|null $description
 */
class CreditTransaction extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
