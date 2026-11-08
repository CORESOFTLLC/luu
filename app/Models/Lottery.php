<?php

namespace App\Models;

use Database\Factories\LotteryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'image', 'description', 'bank_account', 'bank_name', 'location', 'is_active', 'price_per_ticket', 'max_tickets_per_transaction', 'show_ticket_numbers', 'total_tickets', 'sold_tickets', 'starts_at', 'draws_at', 'valid_from', 'valid_until'])]
class Lottery extends Model
{
    /** @use HasFactory<LotteryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'draws_at' => 'datetime',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'price_per_ticket' => 'integer',
            'total_tickets' => 'integer',
            'max_tickets_per_transaction' => 'integer',
            'show_ticket_numbers' => 'boolean',
            'sold_tickets' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<LotteryTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(LotteryTicket::class);
    }

    /**
     * @return HasMany<LotteryImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(LotteryImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<LotteryBank, $this>
     */
    public function bankEntries(): HasMany
    {
        return $this->hasMany(LotteryBank::class);
    }

    /**
     * @return HasMany<LotteryInvalidationPeriod, $this>
     */
    public function invalidationPeriods(): HasMany
    {
        return $this->hasMany(LotteryInvalidationPeriod::class);
    }

    /**
     * @return HasMany<InvalidatedTransfer, $this>
     */
    public function invalidatedTransfers(): HasMany
    {
        return $this->hasMany(InvalidatedTransfer::class);
    }

    /**
     * Re-read this lottery with a row lock held until the surrounding transaction ends,
     * so imports, invalidations and exclusion toggles of one lottery run one at a time.
     */
    public function lockForWriting(): static
    {
        return static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Numbers of tickets deleted by a bulk invalidation stay retired, because tickets
     * can be shown publicly by number.
     */
    public function nextTicketNumber(): int
    {
        return max(
            (int) $this->tickets()->max('ticket_number'),
            (int) $this->invalidationPeriods()->max('last_ticket_number'),
        ) + 1;
    }

    public function completionPercentage(): int
    {
        if (! $this->total_tickets) {
            return 0;
        }

        return (int) round(($this->sold_tickets / $this->total_tickets) * 100);
    }

    public function isUnlimited(): bool
    {
        return $this->total_tickets === null;
    }

    public function isFinished(): bool
    {
        return $this->draws_at->isPast();
    }
}
