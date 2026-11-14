<?php

namespace App\Services;

use App\Models\InvalidatedTransfer;
use App\Models\Lottery;
use App\Models\LotteryBank;
use App\Models\LotteryInvalidationPeriod;
use App\Models\LotteryTicket;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Date windows are bank-local calendar days (inclusive). transacted_at holds the bank's
 * wall-clock time, so a day is the half-open range [D 00:00:00, D+1 00:00:00).
 */
class LotteryInvalidationService
{
    /**
     * @return array{transactions: int, tickets: int, amount: int, already_excluded: int, already_closed: bool, overlapping_periods: list<string>}
     */
    public function preview(Lottery $lottery, string $startsOn, string $endsOn): array
    {
        $affected = $this->activeTransfersIn($lottery, $startsOn, $endsOn);
        $periods = $this->closedPeriods($lottery);

        return [
            'transactions' => (clone $affected)->count(),
            'tickets' => $this->ticketsOf($lottery, $affected)->count(),
            'amount' => (int) (clone $affected)->sum('amount'),
            'already_excluded' => $this->transfersIn($lottery, $startsOn, $endsOn)->where('is_excluded', true)->count(),
            'already_closed' => $this->isFullyClosed($periods, $startsOn, $endsOn),
            'overlapping_periods' => $periods
                ->filter(fn (LotteryInvalidationPeriod $period) => $this->overlaps($period, $startsOn, $endsOn))
                ->map(fn (LotteryInvalidationPeriod $period) => $period->starts_on->toDateString().' – '.$period->ends_on->toDateString())
                ->values()
                ->all(),
        ];
    }

    /**
     * Close the window and invalidate every active transfer of the lottery inside it,
     * regardless of any search, filter or pagination used on the admin screen.
     *
     * @throws ValidationException
     */
    public function invalidate(
        Lottery $lottery,
        User $user,
        string $startsOn,
        string $endsOn,
        string $note,
        int $expectedTransactions,
        int $expectedTickets,
    ): LotteryInvalidationPeriod {
        return DB::transaction(function () use ($lottery, $user, $startsOn, $endsOn, $note, $expectedTransactions, $expectedTickets) {
            $lottery = $lottery->lockForWriting();

            if ($this->isFullyClosed($this->closedPeriods($lottery), $startsOn, $endsOn)) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Энэ хугацаа аль хэдийн бүхэлдээ хүчингүй болсон байна.',
                ]);
            }

            $affected = $this->activeTransfersIn($lottery, $startsOn, $endsOn);
            $transactionsCount = (clone $affected)->count();
            $ticketNumbers = $this->ticketsOf($lottery, $affected)->pluck('ticket_number');

            if ($transactionsCount !== $expectedTransactions || $ticketNumbers->count() !== $expectedTickets) {
                throw ValidationException::withMessages([
                    'confirm' => 'Гүйлгээ эсвэл сугалааны тоо өөрчлөгдсөн байна. Шинэ тоог шалгаад дахин баталгаажуулна уу.',
                ]);
            }

            $period = $lottery->invalidationPeriods()->create([
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'note' => $note,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'transactions_count' => $transactionsCount,
                'tickets_count' => $ticketNumbers->count(),
                'amount_total' => (int) (clone $affected)->sum('amount'),
                'last_ticket_number' => $lottery->nextTicketNumber() - 1,
            ]);

            (clone $affected)
                ->select(['id', 'lottery_id', 'bank_reference', 'transfer_identity', 'phone', 'amount', 'transacted_at'])
                ->chunkById(500, fn (Collection $transactions) => $this->writePeriodTombstones($period, $transactions));

            $affectedIds = (clone $affected)->select('id');

            foreach ($ticketNumbers->chunk(1000) as $chunk) {
                LotteryTicket::query()
                    ->where('lottery_id', $lottery->id)
                    ->whereIn('ticket_number', $chunk->all())
                    ->delete();

                // Combined remainder tickets can also hold remainders of transfers outside the
                // window; free those remainders so a later import can combine them again.
                LotteryBank::query()
                    ->where('lottery_id', $lottery->id)
                    ->whereIn('ticket_number', $chunk->all())
                    ->whereNotIn('transaction_id', $affectedIds)
                    ->update(['ticket_number' => null]);
            }

            LotteryBank::query()
                ->where('lottery_id', $lottery->id)
                ->whereIn('transaction_id', $affectedIds)
                ->update(['is_excluded' => true, 'ticket_number' => null]);

            (clone $affected)->update(['is_excluded' => true]);

            $this->decrementSoldTickets($lottery, $ticketNumbers->count());

            return $period;
        });
    }

    /**
     * @throws ValidationException
     */
    public function updateValidPeriod(Lottery $lottery, ?string $validFrom, ?string $validUntil): void
    {
        DB::transaction(function () use ($lottery, $validFrom, $validUntil) {
            $lottery = $lottery->lockForWriting();

            if ($validFrom !== null && $validUntil !== null) {
                $overlapping = $this->closedPeriods($lottery)
                    ->first(fn (LotteryInvalidationPeriod $period) => $this->overlaps($period, $validFrom, $validUntil));

                if ($overlapping !== null) {
                    throw ValidationException::withMessages([
                        'valid_from' => 'Хүчинтэй хугацаа хүчингүй болгосон '
                            .$overlapping->starts_on->toDateString().' – '.$overlapping->ends_on->toDateString()
                            .' хугацаатай давхцаж болохгүй.',
                    ]);
                }
            }

            $lottery->update(['valid_from' => $validFrom, 'valid_until' => $validUntil]);
        });
    }

    /**
     * @return Collection<int, LotteryInvalidationPeriod>
     */
    public function closedPeriods(Lottery $lottery): Collection
    {
        return $lottery->invalidationPeriods()->orderBy('starts_on')->get(['id', 'lottery_id', 'starts_on', 'ends_on']);
    }

    /**
     * @param  Collection<int, LotteryInvalidationPeriod>  $periods
     */
    public function periodCovering(Collection $periods, string $date): ?LotteryInvalidationPeriod
    {
        return $periods->first(fn (LotteryInvalidationPeriod $period) => $period->covers($date));
    }

    /**
     * The tombstone of an earlier invalidated transfer that a statement row repeats after
     * the transfer's own row was deleted. Tombstones still linked to a live row never match:
     * a row repeating that transfer is caught as a duplicate before this check. Matching errs
     * on the side of blocking: the bank reference alone is enough, and the balance-based
     * identity also matches a reworded description as long as phone and amount agree.
     */
    public function findTombstone(Lottery $lottery, string $reference, ?string $identity, ?string $phone, int $amount): ?InvalidatedTransfer
    {
        return $lottery->invalidatedTransfers()
            ->whereNull('transaction_id')
            ->where(function (Builder $query) use ($reference, $identity, $phone, $amount) {
                $query->where('bank_reference', $reference);

                if ($identity !== null) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('transfer_identity', $identity)
                        ->where('phone', $phone)
                        ->where('amount', $amount));
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * A transfer inside a closed window (or invalidated by one) can never be restored.
     */
    public function isLocked(Transaction $transaction): bool
    {
        $covering = $this->periodCovering(
            $this->closedPeriods($transaction->lottery),
            $transaction->transacted_at->toDateString(),
        );

        return $covering !== null || InvalidatedTransfer::query()
            ->where('transaction_id', $transaction->id)
            ->where('reason', InvalidatedTransfer::REASON_PERIOD)
            ->exists();
    }

    public function tombstoneManually(Transaction $transaction): void
    {
        if (InvalidatedTransfer::query()->where('transaction_id', $transaction->id)->exists()) {
            return;
        }

        InvalidatedTransfer::query()->insert($this->tombstoneRow($transaction, InvalidatedTransfer::REASON_MANUAL));
    }

    public function tombstoneForPeriod(Transaction $transaction, LotteryInvalidationPeriod $period): void
    {
        InvalidatedTransfer::query()->insert($this->tombstoneRow($transaction, InvalidatedTransfer::REASON_PERIOD, $period));
    }

    public function releaseManualTombstone(Transaction $transaction): void
    {
        InvalidatedTransfer::query()
            ->where('transaction_id', $transaction->id)
            ->where('reason', InvalidatedTransfer::REASON_MANUAL)
            ->delete();
    }

    /**
     * Keep every currently excluded transfer blocked before its source rows are deleted,
     * including exclusions made before tombstones existed.
     */
    public function tombstoneExcludedTransfers(Lottery $lottery): void
    {
        $lottery->transactions()
            ->where(fn (Builder $query) => $query
                ->where('is_excluded', true)
                ->orWhereIn('id', LotteryBank::query()
                    ->select('transaction_id')
                    ->where('lottery_id', $lottery->id)
                    ->where('is_excluded', true)))
            ->whereNotIn('id', InvalidatedTransfer::query()
                ->select('transaction_id')
                ->where('lottery_id', $lottery->id)
                ->whereNotNull('transaction_id'))
            ->select(['id', 'lottery_id', 'bank_reference', 'transfer_identity', 'phone', 'amount', 'transacted_at'])
            ->chunkById(500, fn (Collection $transactions) => InvalidatedTransfer::query()->insert($transactions
                ->map(fn (Transaction $transaction) => $this->tombstoneRow($transaction, InvalidatedTransfer::REASON_MANUAL))
                ->all()));
    }

    /**
     * @return array{can_manage: bool, today: string, valid_from: string|null, valid_until: string|null, valid_period_overlaps_closed: bool, active_outside_valid_period: int|null, periods: list<array<string, mixed>>}
     */
    public function pageData(Lottery $lottery, User $user): array
    {
        $periods = $lottery->invalidationPeriods()->latest('id')->get();
        $validFrom = $lottery->valid_from?->toDateString();
        $validUntil = $lottery->valid_until?->toDateString();
        $hasValidPeriod = $validFrom !== null && $validUntil !== null;

        return [
            'can_manage' => $user->can('manage-lottery-invalidations'),
            'today' => $this->today(),
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'valid_period_overlaps_closed' => $hasValidPeriod && $periods->contains(
                fn (LotteryInvalidationPeriod $period) => $this->overlaps($period, $validFrom, $validUntil),
            ),
            'active_outside_valid_period' => $hasValidPeriod
                ? $lottery->transactions()
                    ->where('is_excluded', false)
                    ->where(fn (Builder $query) => $query
                        ->where('transacted_at', '<', $this->dayStart($validFrom))
                        ->orWhere('transacted_at', '>=', $this->dayStart($this->nextDay($validUntil))))
                    ->count()
                : null,
            'periods' => $periods->map(fn (LotteryInvalidationPeriod $period) => [
                'id' => $period->id,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'note' => $period->note,
                'user_name' => $period->user_name,
                'transactions_count' => $period->transactions_count,
                'tickets_count' => $period->tickets_count,
                'amount_total' => $period->amount_total,
                'created_at' => $period->created_at->timezone($this->timezone())->format('Y-m-d H:i'),
            ])->all(),
        ];
    }

    public function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    /**
     * The default also covers a config cache built before config/lottery.php existed.
     */
    public function timezone(): string
    {
        return config()->string('lottery.bank_timezone', 'Asia/Ulaanbaatar');
    }

    /**
     * @return HasMany<Transaction, Lottery>
     */
    private function transfersIn(Lottery $lottery, string $startsOn, string $endsOn): HasMany
    {
        return $lottery->transactions()
            ->where('transacted_at', '>=', $this->dayStart($startsOn))
            ->where('transacted_at', '<', $this->dayStart($this->nextDay($endsOn)));
    }

    /**
     * @return HasMany<Transaction, Lottery>
     */
    private function activeTransfersIn(Lottery $lottery, string $startsOn, string $endsOn): HasMany
    {
        return $this->transfersIn($lottery, $startsOn, $endsOn)->where('is_excluded', false);
    }

    /**
     * Tickets funded by the given transfers: their own tickets plus combined remainder
     * tickets that include one of their remainders.
     *
     * @param  HasMany<Transaction, Lottery>  $transfers
     * @return Builder<LotteryTicket>
     */
    private function ticketsOf(Lottery $lottery, HasMany $transfers): Builder
    {
        $transferIds = (clone $transfers)->select('id');

        return LotteryTicket::query()
            ->where('lottery_id', $lottery->id)
            ->where(fn (Builder $query) => $query
                ->whereIn('transaction_id', $transferIds)
                ->orWhereIn('ticket_number', LotteryBank::query()
                    ->select('ticket_number')
                    ->where('lottery_id', $lottery->id)
                    ->whereNotNull('ticket_number')
                    ->whereIn('transaction_id', $transferIds)));
    }

    /**
     * @param  Collection<int, LotteryInvalidationPeriod>  $periods
     */
    private function isFullyClosed(Collection $periods, string $startsOn, string $endsOn): bool
    {
        $uncovered = $startsOn;

        foreach ($periods as $period) {
            if ($period->ends_on->toDateString() < $uncovered) {
                continue;
            }

            if ($period->starts_on->toDateString() > $uncovered) {
                return false;
            }

            $uncovered = $this->nextDay($period->ends_on->toDateString());

            if ($uncovered > $endsOn) {
                return true;
            }
        }

        return false;
    }

    private function overlaps(LotteryInvalidationPeriod $period, string $startsOn, string $endsOn): bool
    {
        return $period->starts_on->toDateString() <= $endsOn && $startsOn <= $period->ends_on->toDateString();
    }

    /**
     * Manual tombstones of these transfers (single exclusions of bank entries) become period
     * tombstones, so they can no longer be released by a restore.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function writePeriodTombstones(LotteryInvalidationPeriod $period, Collection $transactions): void
    {
        $linked = InvalidatedTransfer::query()->whereIn('transaction_id', $transactions->modelKeys());

        (clone $linked)->update([
            'reason' => InvalidatedTransfer::REASON_PERIOD,
            'lottery_invalidation_period_id' => $period->id,
        ]);

        $tombstoned = array_flip((clone $linked)->pluck('transaction_id')->map(fn ($id) => (int) $id)->all());

        InvalidatedTransfer::query()->insert($transactions
            ->reject(fn (Transaction $transaction) => isset($tombstoned[$transaction->id]))
            ->map(fn (Transaction $transaction) => $this->tombstoneRow($transaction, InvalidatedTransfer::REASON_PERIOD, $period))
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function tombstoneRow(Transaction $transaction, string $reason, ?LotteryInvalidationPeriod $period = null): array
    {
        return [
            'lottery_id' => $transaction->lottery_id,
            'lottery_invalidation_period_id' => $period?->id,
            'transaction_id' => $transaction->id,
            'reason' => $reason,
            'bank_reference' => $transaction->bank_reference,
            'transfer_identity' => $transaction->transfer_identity,
            'phone' => $transaction->phone,
            'amount' => $transaction->amount,
            'transacted_at' => $transaction->transacted_at->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function decrementSoldTickets(Lottery $lottery, int $count): void
    {
        if ($count === 0) {
            return;
        }

        Lottery::query()->whereKey($lottery->id)->update([
            'sold_tickets' => DB::raw("CASE WHEN sold_tickets > {$count} THEN sold_tickets - {$count} ELSE 0 END"),
        ]);
    }

    private function dayStart(string $date): string
    {
        return $date.' 00:00:00';
    }

    private function nextDay(string $date): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date)->addDay()->toDateString();
    }
}
