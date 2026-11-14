<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable record of an invalidated bank transfer. It outlives the source transaction
 * row so a re-imported statement can never turn the transfer back into tickets.
 * transaction_id points at the live row of the transfer: it is cleared when that row
 * is deleted and set again when the statement row is imported anew.
 */
#[Fillable(['lottery_id', 'lottery_invalidation_period_id', 'transaction_id', 'reason', 'bank_reference', 'transfer_identity', 'phone', 'amount', 'transacted_at'])]
class InvalidatedTransfer extends Model
{
    public const REASON_PERIOD = 'period';

    public const REASON_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transacted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lottery, $this>
     */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /**
     * @return BelongsTo<LotteryInvalidationPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(LotteryInvalidationPeriod::class, 'lottery_invalidation_period_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
