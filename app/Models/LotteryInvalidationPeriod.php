<?php

namespace App\Models;

use Database\Factories\LotteryInvalidationPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A closed bank-local date window (inclusive) whose transfers no longer produce tickets.
 * Each row is also the history entry of the bulk invalidation that closed it, and keeps
 * the highest ticket number issued so far so numbers of deleted tickets are never reused.
 */
#[Fillable(['lottery_id', 'starts_on', 'ends_on', 'note', 'user_id', 'user_name', 'transactions_count', 'tickets_count', 'amount_total', 'last_ticket_number'])]
class LotteryInvalidationPeriod extends Model
{
    /** @use HasFactory<LotteryInvalidationPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'transactions_count' => 'integer',
            'tickets_count' => 'integer',
            'amount_total' => 'integer',
            'last_ticket_number' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InvalidatedTransfer, $this>
     */
    public function invalidatedTransfers(): HasMany
    {
        return $this->hasMany(InvalidatedTransfer::class);
    }

    public function covers(string $date): bool
    {
        return $this->starts_on->toDateString() <= $date && $date <= $this->ends_on->toDateString();
    }
}
