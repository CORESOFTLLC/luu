<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lottery_id', 'bank_reference', 'transfer_identity', 'phone', 'amount', 'refund_amount', 'description', 'transacted_at', 'is_excluded'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transacted_at' => 'datetime',
            'amount' => 'integer',
            'refund_amount' => 'integer',
            'is_excluded' => 'boolean',
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
     * @return HasMany<LotteryTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(LotteryTicket::class);
    }
}
