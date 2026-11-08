<?php

namespace App\Models;

use Database\Factories\LotteryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'image', 'description', 'bank_account', 'bank_name', 'location', 'is_active', 'price_per_ticket', 'max_tickets_per_transaction', 'show_ticket_numbers', 'total_tickets', 'sold_tickets', 'starts_at', 'draws_at'])]
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
