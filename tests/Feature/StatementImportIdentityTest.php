<?php

use App\Models\Lottery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 04:00:00', 'UTC'));

    $this->admin = User::factory()->create();
    config(['lottery.invalidation_admin_ids' => [$this->admin->id]]);

    $this->lottery = Lottery::factory()->active()->create([
        'price_per_ticket' => 15000,
        'total_tickets' => null,
        'max_tickets_per_transaction' => null,
    ]);

    $this->actingAs($this->admin);
});

/**
 * Two transfers that share second, amount and description and differ only in the
 * account's running balance.
 *
 * @return list<array{0: string, 1: int, 2: string, 3: float, 4: float}>
 */
function sameSecondTwins(): array
{
    return [
        ['2026-11-16 09:00:00', 15000, '99000008 сугалаа', 100000.0, 115000.0],
        ['2026-11-16 09:00:00', 15000, '99000008 сугалаа', 115000.0, 130000.0],
    ];
}

test('identical transfers in the same second are told apart by the running balance', function () {
    expect(importStatement($this->lottery, sameSecondTwins()))
        ->toBe(['imported' => 2, 'tickets_created' => 2, 'skipped' => 0, 'invalidated' => 0]);

    [$first, $second] = $this->lottery->transactions()->orderBy('id')->get()->all();

    expect($second->bank_reference)->toBe($first->bank_reference.'#2')
        ->and($first->transfer_identity)->not->toBeNull()
        ->and($second->transfer_identity)->not->toBe($first->transfer_identity);

    expect(importStatement($this->lottery, sameSecondTwins()))
        ->toBe(['imported' => 0, 'tickets_created' => 0, 'skipped' => 2, 'invalidated' => 0]);
});

test('a row repeated with the same balances is imported once', function () {
    $row = sameSecondTwins()[0];

    expect(importStatement($this->lottery, [$row, $row]))
        ->toBe(['imported' => 1, 'tickets_created' => 1, 'skipped' => 1, 'invalidated' => 0]);
});

test('statements without balance columns keep the previous duplicate handling', function () {
    $rows = [
        ['2026-11-16 09:00:00', 15000, '99000008 сугалаа'],
        ['2026-11-16 09:00:00', 15000, '99000008 сугалаа'],
    ];

    expect(importStatement($this->lottery, $rows, withBalances: false))
        ->toBe(['imported' => 1, 'tickets_created' => 1, 'skipped' => 1, 'invalidated' => 0])
        ->and($this->lottery->transactions()->sole()->transfer_identity)->toBeNull();
});

test('a reworded description of an imported transfer is not imported twice', function () {
    importStatement($this->lottery, [['2026-11-16 11:00:00', 15000, '99000009 сугалаа', 100000.0, 115000.0]]);

    expect(importStatement($this->lottery, [['2026-11-16 11:00:00', 15000, '99000009 СУГАЛАА авав', 100000.0, 115000.0]]))
        ->toBe(['imported' => 0, 'tickets_created' => 0, 'skipped' => 1, 'invalidated' => 0])
        ->and($this->lottery->transactions()->count())->toBe(1);
});

test('an excluded transfer is recognised after clearing even when its description was reworded', function () {
    importStatement($this->lottery, [['2026-11-16 11:00:00', 15000, '99000009 сугалаа', 100000.0, 115000.0]]);
    $transaction = $this->lottery->transactions()->sole();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude");
    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect(importStatement($this->lottery, [['2026-11-16 11:00:00', 15000, '99000009 сугалаа авав', 100000.0, 115000.0]]))
        ->toBe(['imported' => 1, 'tickets_created' => 0, 'skipped' => 0, 'invalidated' => 1]);
});

test('excluding one of two same-second twins keeps the other one valid after clearing and reimporting', function () {
    importStatement($this->lottery, sameSecondTwins());
    $second = $this->lottery->transactions()->orderByDesc('id')->first();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$second->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Гүйлгээ цуцлагдлаа.');
    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect(importStatement($this->lottery, sameSecondTwins()))
        ->toBe(['imported' => 2, 'tickets_created' => 1, 'skipped' => 0, 'invalidated' => 1]);

    $excluded = $this->lottery->transactions()->where('is_excluded', true)->sole();

    expect($excluded->bank_reference)->toEndWith('#2')
        ->and($excluded->transfer_identity)->toBe($second->transfer_identity);

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$excluded->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Гүйлгээ сэргээгдлээ.');

    expect($this->lottery->tickets()->count())->toBe(2)
        ->and($this->lottery->invalidatedTransfers()->exists())->toBeFalse();
});

test('an excluded twin is still blocked when a later export lists the twins in the other order', function () {
    importStatement($this->lottery, sameSecondTwins());
    $second = $this->lottery->transactions()->orderByDesc('id')->first();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$second->id}/toggle-exclude");
    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect(importStatement($this->lottery, array_reverse(sameSecondTwins())))
        ->toBe(['imported' => 2, 'tickets_created' => 1, 'skipped' => 0, 'invalidated' => 1]);

    $excluded = $this->lottery->transactions()->where('is_excluded', true)->sole();

    expect($excluded->transfer_identity)->toBe($second->transfer_identity)
        ->and($this->lottery->invalidatedTransfers()->sole()->transaction_id)->toBe($excluded->id);
});
