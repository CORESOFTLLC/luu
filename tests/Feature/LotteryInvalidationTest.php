<?php

use App\Models\InvalidatedTransfer;
use App\Models\Lottery;
use App\Models\LotteryBank;
use App\Models\LotteryInvalidationPeriod;
use App\Models\LotteryTicket;
use App\Models\User;
use App\Services\LotteryInvalidationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 04:00:00', 'UTC'));

    $this->admin = User::factory()->create(['name' => 'Ахлах админ']);
    config(['lottery.invalidation_admin_ids' => [$this->admin->id]]);

    $this->lottery = Lottery::factory()->active()->create([
        'price_per_ticket' => 15000,
        'total_tickets' => null,
        'max_tickets_per_transaction' => null,
    ]);

    $this->actingAs($this->admin);
});

/**
 * Transfers around the 2026-11-10 – 2026-11-15 window: 8 tickets in total, of which
 * 3 transfers with 6 tickets fall inside the window.
 *
 * @return list<array{0: string, 1: int, 2: string}>
 */
function windowRows(): array
{
    return [
        ['2026-11-09 23:59:59', 15000, '99000001 сугалаа'],
        ['2026-11-10 00:00:00', 30000, '99000002 сугалаа'],
        ['2026-11-12 13:00:00', 15000, '99000003 сугалаа'],
        ['2026-11-15 23:59:59', 45000, '99000004 сугалаа'],
        ['2026-11-16 00:00:00', 15000, '99000005 сугалаа'],
    ];
}

function previewWindow(Lottery $lottery, string $startsOn, string $endsOn): TestResponse
{
    return test()->getJson("/admin/lotteries/{$lottery->id}/invalidations/preview?starts_on={$startsOn}&ends_on={$endsOn}");
}

function closeWindow(Lottery $lottery, string $startsOn, string $endsOn, string $note = 'Тохирол явагдсан тул хаав.'): TestResponse
{
    $preview = previewWindow($lottery, $startsOn, $endsOn)->assertOk()->json();

    return test()->post("/admin/lotteries/{$lottery->id}/invalidations", [
        'starts_on' => $startsOn,
        'ends_on' => $endsOn,
        'note' => $note,
        'confirm' => 'батлах',
        'expected_transactions' => $preview['transactions'],
        'expected_tickets' => $preview['tickets'],
    ]);
}

/**
 * @return list<string>
 */
function activePhones(Lottery $lottery): array
{
    return $lottery->transactions()->where('is_excluded', false)->orderBy('transacted_at')->pluck('phone')->all();
}

/**
 * @return list<string>
 */
function ticketPhones(Lottery $lottery): array
{
    return $lottery->tickets()->orderBy('ticket_number')->pluck('phone')->all();
}

test('preview counts every active transfer of the inclusive bank-local window', function () {
    importStatement($this->lottery, windowRows());

    previewWindow($this->lottery, '2026-11-10', '2026-11-15')
        ->assertOk()
        ->assertExactJson([
            'transactions' => 3,
            'tickets' => 6,
            'amount' => 90000,
            'already_excluded' => 0,
            'already_closed' => false,
            'overlapping_periods' => [],
        ]);

    previewWindow($this->lottery, '2026-11-09', '2026-11-09')->assertJson(['transactions' => 1, 'tickets' => 1]);
    previewWindow($this->lottery, '2026-11-16', '2026-11-16')->assertJson(['transactions' => 1, 'tickets' => 1]);
});

test('bulk invalidation keeps the source records, removes their tickets and records history', function () {
    importStatement($this->lottery, windowRows());

    closeWindow($this->lottery, '2026-11-10', '2026-11-15', 'Эхний тохирол 11/15-нд явагдсан.')
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', '3 гүйлгээ, 6 сугалаа хүчингүй боллоо.');

    $lottery = $this->lottery->fresh();

    expect($lottery->transactions()->count())->toBe(5)
        ->and(activePhones($lottery))->toBe(['99000001', '99000005'])
        ->and(ticketPhones($lottery))->toBe(['99000001', '99000005'])
        ->and($lottery->sold_tickets)->toBe(2)
        ->and($lottery->bankEntries()->where('is_excluded', true)->count())->toBe(6)
        ->and($lottery->bankEntries()->where('is_excluded', true)->whereNotNull('ticket_number')->exists())->toBeFalse();

    $period = $lottery->invalidationPeriods()->sole();

    expect($period->starts_on->toDateString())->toBe('2026-11-10')
        ->and($period->ends_on->toDateString())->toBe('2026-11-15')
        ->and($period->note)->toBe('Эхний тохирол 11/15-нд явагдсан.')
        ->and($period->user_id)->toBe($this->admin->id)
        ->and($period->user_name)->toBe('Ахлах админ')
        ->and($period->transactions_count)->toBe(3)
        ->and($period->tickets_count)->toBe(6)
        ->and($period->amount_total)->toBe(90000)
        ->and($period->last_ticket_number)->toBe(8)
        ->and($period->created_at->toDateTimeString())->toBe('2026-11-20 04:00:00');

    expect($lottery->invalidatedTransfers()->where('reason', InvalidatedTransfer::REASON_PERIOD)->orderBy('phone')->pluck('phone')->all())
        ->toBe(['99000002', '99000003', '99000004']);
});

test('the import page shows the valid period and the invalidation history', function () {
    importStatement($this->lottery, windowRows());
    $this->lottery->update(['valid_from' => '2026-11-16', 'valid_until' => '2026-11-30']);
    closeWindow($this->lottery, '2026-11-10', '2026-11-15', 'Эхний тохирол.');

    $this->get("/admin/lotteries/{$this->lottery->id}/import")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/lotteries/import')
            ->where('invalidation.can_manage', true)
            ->where('invalidation.today', '2026-11-20')
            ->where('invalidation.valid_from', '2026-11-16')
            ->where('invalidation.valid_until', '2026-11-30')
            ->where('invalidation.valid_period_overlaps_closed', false)
            ->where('invalidation.active_outside_valid_period', 1)
            ->has('invalidation.periods', 1)
            ->where('invalidation.periods.0.starts_on', '2026-11-10')
            ->where('invalidation.periods.0.ends_on', '2026-11-15')
            ->where('invalidation.periods.0.note', 'Эхний тохирол.')
            ->where('invalidation.periods.0.user_name', 'Ахлах админ')
            ->where('invalidation.periods.0.transactions_count', 3)
            ->where('invalidation.periods.0.tickets_count', 6)
            ->where('invalidation.periods.0.created_at', '2026-11-20 12:00')
        );
});

test('overlapping windows only invalidate what is still active and a closed window cannot be closed again', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-10', '2026-11-15')->assertSessionHasNoErrors();

    importStatement($this->lottery, [
        ['2026-11-16 10:00:00', 15000, '99000013 сугалаа'],
        ['2026-11-17 10:00:00', 30000, '99000014 сугалаа'],
    ]);

    previewWindow($this->lottery, '2026-11-14', '2026-11-17')->assertExactJson([
        'transactions' => 3,
        'tickets' => 4,
        'amount' => 60000,
        'already_excluded' => 1,
        'already_closed' => false,
        'overlapping_periods' => ['2026-11-10 – 2026-11-15'],
    ]);

    closeWindow($this->lottery, '2026-11-14', '2026-11-17')->assertSessionHasNoErrors();

    previewWindow($this->lottery, '2026-11-10', '2026-11-17')->assertJson([
        'transactions' => 0,
        'tickets' => 0,
        'already_closed' => true,
    ]);

    closeWindow($this->lottery, '2026-11-10', '2026-11-17')->assertSessionHasErrors('starts_on');
    closeWindow($this->lottery, '2026-11-12', '2026-11-12')->assertSessionHasErrors('starts_on');

    expect($this->lottery->invalidationPeriods()->count())->toBe(2)
        ->and(activePhones($this->lottery))->toBe(['99000001'])
        ->and($this->lottery->fresh()->sold_tickets)->toBe(1);
});

test('a confirmation based on outdated counts is rejected without changing anything', function () {
    importStatement($this->lottery, windowRows());
    $preview = previewWindow($this->lottery, '2026-11-10', '2026-11-15')->json();

    importStatement($this->lottery, [['2026-11-11 08:00:00', 15000, '99000015 сугалаа']]);

    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations", [
        'starts_on' => '2026-11-10',
        'ends_on' => '2026-11-15',
        'note' => 'Хуучин тоогоор баталгаажуулав.',
        'confirm' => 'батлах',
        'expected_transactions' => $preview['transactions'],
        'expected_tickets' => $preview['tickets'],
    ])->assertSessionHasErrors('confirm');

    expect($this->lottery->invalidationPeriods()->exists())->toBeFalse()
        ->and($this->lottery->transactions()->where('is_excluded', true)->exists())->toBeFalse()
        ->and($this->lottery->tickets()->count())->toBe(9);

    closeWindow($this->lottery, '2026-11-10', '2026-11-15')
        ->assertInertiaFlash('toast.message', '4 гүйлгээ, 7 сугалаа хүчингүй боллоо.');
});

test('submitting the same invalidation twice closes the window only once', function () {
    importStatement($this->lottery, windowRows());
    $payload = [
        'starts_on' => '2026-11-10',
        'ends_on' => '2026-11-15',
        'note' => 'Давхар илгээлт шалгах.',
        'confirm' => 'батлах',
        'expected_transactions' => 3,
        'expected_tickets' => 6,
    ];

    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations", $payload)->assertSessionHasNoErrors();
    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations", $payload)->assertSessionHasErrors('starts_on');

    expect($this->lottery->invalidationPeriods()->count())->toBe(1)
        ->and($this->lottery->fresh()->sold_tickets)->toBe(2);
});

test('bulk invalidation covers every transfer in the window regardless of filters and pagination', function () {
    importStatement($this->lottery, collect(range(1, 25))
        ->map(fn (int $i) => [sprintf('2026-11-12 10:%02d:00', $i), 15000, sprintf('990001%02d сугалаа', $i)])
        ->all());

    $this->get("/admin/lotteries/{$this->lottery->id}/import?search=99000101&status=active")
        ->assertInertia(fn (Assert $page) => $page->has('recentTransactions.data', 1));

    $this->get("/admin/lotteries/{$this->lottery->id}/import")
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions.data', 20)
            ->where('recentTransactions.total', 25));

    closeWindow($this->lottery, '2026-11-12', '2026-11-12')
        ->assertInertiaFlash('toast.message', '25 гүйлгээ, 25 сугалаа хүчингүй боллоо.');

    expect($this->lottery->transactions()->where('is_excluded', false)->exists())->toBeFalse()
        ->and($this->lottery->tickets()->exists())->toBeFalse();
});

test('reimporting the same statement never revives invalidated transfers', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    expect(importStatement($this->lottery, windowRows()))
        ->toBe(['imported' => 0, 'tickets_created' => 0, 'skipped' => 5, 'invalidated' => 0])
        ->and(activePhones($this->lottery))->toBe(['99000001', '99000005'])
        ->and($this->lottery->tickets()->count())->toBe(2);
});

test('invalidated transfers stay invalid after their rows are deleted and imported again', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    $this->delete("/admin/lotteries/{$this->lottery->id}/import")->assertRedirect();

    expect($this->lottery->transactions()->exists())->toBeFalse()
        ->and($this->lottery->invalidationPeriods()->count())->toBe(1)
        ->and($this->lottery->invalidatedTransfers()->count())->toBe(3)
        ->and($this->lottery->invalidatedTransfers()->whereNotNull('transaction_id')->exists())->toBeFalse();

    $this->post("/admin/lotteries/{$this->lottery->id}/import", ['file' => khanStatement(windowRows())])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', '5 гүйлгээ импорт хийгдлээ. 2 сугалаа үүслээ. 0 алгаслаа. 3 гүйлгээ өмнө хүчингүй болсон тул сугалаа үүсгээгүй.');

    expect(activePhones($this->lottery))->toBe(['99000001', '99000005'])
        ->and(ticketPhones($this->lottery))->toBe(['99000001', '99000005'])
        ->and($this->lottery->tickets()->orderBy('ticket_number')->pluck('ticket_number')->all())->toBe([9, 10])
        ->and($this->lottery->bankEntries()->where('is_excluded', true)->count())->toBe(6)
        ->and($this->lottery->fresh()->sold_tickets)->toBe(2)
        ->and($this->lottery->invalidatedTransfers()->whereNull('transaction_id')->exists())->toBeFalse();
});

test('old transfers imported later into a closed window never create tickets', function () {
    closeWindow($this->lottery, '2026-11-01', '2026-11-05', 'Өмнөх тохиролын хугацаа.')
        ->assertInertiaFlash('toast.message', '0 гүйлгээ, 0 сугалаа хүчингүй боллоо.');

    expect(importStatement($this->lottery, [
        ['2026-11-01 00:00:00', 15000, '99000011 сугалаа'],
        ['2026-11-05 23:59:59', 30000, '99000012 сугалаа'],
        ['2026-11-06 00:00:00', 15000, '99000013 сугалаа'],
    ]))->toBe(['imported' => 3, 'tickets_created' => 1, 'skipped' => 0, 'invalidated' => 2]);

    expect(activePhones($this->lottery))->toBe(['99000013'])
        ->and(ticketPhones($this->lottery))->toBe(['99000013'])
        ->and($this->lottery->bankEntries()->where('is_excluded', true)->count())->toBe(3)
        ->and($this->lottery->invalidatedTransfers()->where('reason', InvalidatedTransfer::REASON_PERIOD)->count())->toBe(2);
});

test('repeated transfers with the same phone and amount are tracked separately', function () {
    $rows = [
        ['2026-11-14 10:00:00', 15000, '99000007 сугалаа'],
        ['2026-11-16 10:00:00', 15000, '99000007 сугалаа'],
        ['2026-11-17 10:00:00', 15000, '99000007 сугалаа'],
    ];

    importStatement($this->lottery, $rows);
    expect($this->lottery->tickets()->count())->toBe(3);

    closeWindow($this->lottery, '2026-11-10', '2026-11-15');
    expect($this->lottery->tickets()->count())->toBe(2);

    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect(importStatement($this->lottery, $rows))
        ->toBe(['imported' => 3, 'tickets_created' => 2, 'skipped' => 0, 'invalidated' => 1])
        ->and($this->lottery->transactions()->where('is_excluded', true)->sole()->transacted_at->toDateTimeString())
        ->toBe('2026-11-14 10:00:00');
});

test('single exclusion and restore keep working outside closed windows', function () {
    importStatement($this->lottery, [['2026-11-17 10:00:00', 30000, '99000016 сугалаа']]);
    $transaction = $this->lottery->transactions()->sole();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Гүйлгээ цуцлагдлаа.');

    expect($transaction->fresh()->is_excluded)->toBeTrue()
        ->and($this->lottery->tickets()->exists())->toBeFalse()
        ->and($this->lottery->fresh()->sold_tickets)->toBe(0)
        ->and($this->lottery->invalidatedTransfers()->sole()->reason)->toBe(InvalidatedTransfer::REASON_MANUAL);

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Гүйлгээ сэргээгдлээ.');

    expect($transaction->fresh()->is_excluded)->toBeFalse()
        ->and($this->lottery->tickets()->count())->toBe(2)
        ->and($this->lottery->fresh()->sold_tickets)->toBe(2)
        ->and($this->lottery->invalidatedTransfers()->exists())->toBeFalse();

    $bankEntry = $this->lottery->bankEntries()->first();

    $this->post("/admin/lotteries/{$this->lottery->id}/bank/{$bankEntry->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Сугалаа биш гэж тэмдэглэлээ.');

    expect($this->lottery->tickets()->exists())->toBeFalse()
        ->and($this->lottery->invalidatedTransfers()->sole()->transaction_id)->toBe($transaction->id);

    $this->post("/admin/lotteries/{$this->lottery->id}/bank/{$bankEntry->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Сугалаа сэргээлээ.');

    expect($this->lottery->tickets()->count())->toBe(2)
        ->and($this->lottery->invalidatedTransfers()->exists())->toBeFalse();
});

test('single exclusions survive clearing and reimporting the statement but can still be restored', function () {
    $rows = [['2026-11-17 10:00:00', 30000, '99000016 сугалаа']];
    importStatement($this->lottery, $rows);
    $transaction = $this->lottery->transactions()->sole();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude");
    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect(importStatement($this->lottery, $rows))
        ->toBe(['imported' => 1, 'tickets_created' => 0, 'skipped' => 0, 'invalidated' => 1]);

    $reimported = $this->lottery->transactions()->sole();

    expect($reimported->is_excluded)->toBeTrue()
        ->and($this->lottery->invalidatedTransfers()->sole()->transaction_id)->toBe($reimported->id);

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$reimported->id}/toggle-exclude")
        ->assertInertiaFlash('toast.message', 'Гүйлгээ сэргээгдлээ.');

    expect($this->lottery->tickets()->count())->toBe(2)
        ->and($this->lottery->invalidatedTransfers()->exists())->toBeFalse();
});

test('exclusions made before tombstones existed are kept when the statement is cleared', function () {
    $rows = [
        ['2026-11-17 10:00:00', 15000, '99000041 сугалаа'],
        ['2026-11-17 11:00:00', 15000, '99000042 сугалаа'],
    ];
    importStatement($this->lottery, $rows);

    $excluded = $this->lottery->transactions()->where('phone', '99000041')->sole();
    $excluded->update(['is_excluded' => true]);
    LotteryBank::query()->where('transaction_id', $excluded->id)->update(['is_excluded' => true, 'ticket_number' => null]);
    LotteryTicket::query()->where('transaction_id', $excluded->id)->delete();

    $bankOnly = $this->lottery->transactions()->where('phone', '99000042')->sole();
    LotteryBank::query()->where('transaction_id', $bankOnly->id)->update(['is_excluded' => true, 'ticket_number' => null]);
    LotteryTicket::query()->where('transaction_id', $bankOnly->id)->delete();

    expect($this->lottery->invalidatedTransfers()->exists())->toBeFalse();

    $this->delete("/admin/lotteries/{$this->lottery->id}/import");

    expect($this->lottery->invalidatedTransfers()->where('reason', InvalidatedTransfer::REASON_MANUAL)->count())->toBe(2)
        ->and(importStatement($this->lottery, $rows))->toBe(['imported' => 2, 'tickets_created' => 0, 'skipped' => 0, 'invalidated' => 2]);
});

test('transfers in a closed window cannot be restored one by one', function () {
    importStatement($this->lottery, windowRows());
    $manuallyExcluded = $this->lottery->transactions()->where('phone', '99000002')->sole();
    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$manuallyExcluded->id}/toggle-exclude");

    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    $transaction = $this->lottery->transactions()->where('phone', '99000003')->sole();
    $bankEntry = $this->lottery->bankEntries()->where('phone', '99000004')->first();

    foreach ([
        "/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude",
        "/admin/lotteries/{$this->lottery->id}/transactions/{$manuallyExcluded->id}/toggle-exclude",
        "/admin/lotteries/{$this->lottery->id}/bank/{$bankEntry->id}/toggle-exclude",
    ] as $url) {
        $this->post($url)
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', 'Энэ гүйлгээ хүчингүй болгосон хугацаанд хамаарах тул сэргээх боломжгүй.');
    }

    expect(activePhones($this->lottery))->toBe(['99000001', '99000005'])
        ->and($this->lottery->bankEntries()->where('is_excluded', false)->count())->toBe(2)
        ->and($this->lottery->tickets()->count())->toBe(2)
        ->and($this->lottery->fresh()->sold_tickets)->toBe(2);
});

test('a single bank entry exclusion inside the window becomes permanent when the window is closed', function () {
    importStatement($this->lottery, windowRows());
    $bankEntry = $this->lottery->bankEntries()->where('phone', '99000003')->sole();

    $this->post("/admin/lotteries/{$this->lottery->id}/bank/{$bankEntry->id}/toggle-exclude");
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    expect($this->lottery->invalidatedTransfers()->where('transaction_id', $bankEntry->transaction_id)->sole()->reason)
        ->toBe(InvalidatedTransfer::REASON_PERIOD);
});

test('bulk invalidation only touches the selected lottery', function () {
    $other = Lottery::factory()->active()->create([
        'price_per_ticket' => 15000,
        'total_tickets' => null,
        'max_tickets_per_transaction' => null,
    ]);

    importStatement($this->lottery, windowRows());
    importStatement($other, [
        ['2026-11-12 13:00:00', 15000, '99000021 нөгөө сугалаа'],
        ['2026-11-14 09:00:00', 30000, '99000022 нөгөө сугалаа'],
    ]);

    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    expect($other->transactions()->where('is_excluded', true)->exists())->toBeFalse()
        ->and($other->tickets()->count())->toBe(3)
        ->and($other->fresh()->sold_tickets)->toBe(3)
        ->and($other->invalidationPeriods()->exists())->toBeFalse()
        ->and($other->invalidatedTransfers()->exists())->toBeFalse();

    previewWindow($other, '2026-11-10', '2026-11-15')
        ->assertJson(['transactions' => 2, 'tickets' => 3, 'already_closed' => false]);

    expect(importStatement($other, [['2026-11-13 10:00:00', 15000, '99000023 нөгөө сугалаа']]))
        ->toBe(['imported' => 1, 'tickets_created' => 1, 'skipped' => 0, 'invalidated' => 0]);
});

test('exclusion toggles reject records of another lottery', function () {
    $other = Lottery::factory()->active()->create(['price_per_ticket' => 15000, 'max_tickets_per_transaction' => null]);
    importStatement($other, [['2026-11-17 10:00:00', 15000, '99000031 сугалаа']]);
    $transaction = $other->transactions()->sole();
    $bankEntry = $other->bankEntries()->sole();

    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude")->assertNotFound();
    $this->post("/admin/lotteries/{$this->lottery->id}/bank/{$bankEntry->id}/toggle-exclude")->assertNotFound();

    expect($transaction->fresh()->is_excluded)->toBeFalse()
        ->and($bankEntry->fresh()->is_excluded)->toBeFalse()
        ->and($other->tickets()->count())->toBe(1);
});

test('the valid period is set explicitly and cannot overlap a closed window', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');
    $url = "/admin/lotteries/{$this->lottery->id}/valid-period";

    $this->put($url, ['valid_from' => '2026-11-16', 'valid_until' => '2026-11-30'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Хүчинтэй хугацаа хадгалагдлаа.');

    $this->put($url, ['valid_from' => '2026-11-14', 'valid_until' => '2026-11-30'])->assertSessionHasErrors('valid_from');
    $this->put($url, ['valid_from' => '2026-11-20', 'valid_until' => null])->assertSessionHasErrors('valid_until');
    $this->put($url, ['valid_from' => '2026-11-30', 'valid_until' => '2026-11-20'])->assertSessionHasErrors('valid_until');
    $this->put($url, ['valid_from' => '30/11/2026', 'valid_until' => '2026-12-31'])->assertSessionHasErrors('valid_from');

    $lottery = $this->lottery->fresh();

    expect($lottery->valid_from->toDateString())->toBe('2026-11-16')
        ->and($lottery->valid_until->toDateString())->toBe('2026-11-30');

    $this->put($url, ['valid_from' => null, 'valid_until' => null])->assertSessionHasNoErrors();

    expect($this->lottery->fresh()->valid_from)->toBeNull();
});

test('the history flags a valid period that overlaps a window closed later', function () {
    $this->lottery->update(['valid_from' => '2026-11-10', 'valid_until' => '2026-11-30']);
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    $this->get("/admin/lotteries/{$this->lottery->id}/import")
        ->assertInertia(fn (Assert $page) => $page->where('invalidation.valid_period_overlaps_closed', true));
});

test('a combined remainder ticket that spans the window boundary is invalidated and the other remainder is freed', function () {
    importStatement($this->lottery, [
        ['2026-11-15 20:00:00', 10000, '99000017 сугалаа'],
        ['2026-11-16 08:00:00', 10000, '99000017 сугалаа'],
    ]);

    $outside = $this->lottery->transactions()->where('transacted_at', '2026-11-15 20:00:00')->sole();
    $inside = $this->lottery->transactions()->where('transacted_at', '2026-11-16 08:00:00')->sole();

    expect($this->lottery->tickets()->sole()->transaction_id)->toBe($outside->id);

    previewWindow($this->lottery, '2026-11-16', '2026-11-16')->assertJson(['transactions' => 1, 'tickets' => 1]);
    closeWindow($this->lottery, '2026-11-16', '2026-11-16');

    expect($this->lottery->tickets()->exists())->toBeFalse()
        ->and($outside->fresh()->is_excluded)->toBeFalse()
        ->and(LotteryBank::query()->where('transaction_id', $outside->id)->sole()->ticket_number)->toBeNull()
        ->and(LotteryBank::query()->where('transaction_id', $inside->id)->sole()->is_excluded)->toBeTrue();

    expect(importStatement($this->lottery, [['2026-11-17 09:00:00', 5000, '99000017 сугалаа']]))
        ->toMatchArray(['imported' => 1, 'tickets_created' => 1]);

    $ticket = $this->lottery->tickets()->sole();

    expect($ticket->transaction_id)->toBe($outside->id)
        ->and($ticket->ticket_number)->toBe(2)
        ->and(LotteryBank::query()->where('transaction_id', $inside->id)->sole()->ticket_number)->toBeNull();
});

test('ticket numbers of invalidated tickets are never issued again', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-01', '2026-11-19');

    importStatement($this->lottery, [['2026-11-20 09:00:00', 30000, '99000051 сугалаа']]);

    expect($this->lottery->tickets()->orderBy('ticket_number')->pluck('ticket_number')->all())->toBe([9, 10]);

    $transaction = $this->lottery->transactions()->where('phone', '99000051')->sole();
    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude");
    $this->post("/admin/lotteries/{$this->lottery->id}/transactions/{$transaction->id}/toggle-exclude");

    expect($this->lottery->tickets()->orderBy('ticket_number')->pluck('ticket_number')->all())->toBe([9, 10]);
});

test('bulk invalidation is rolled back completely when a step fails', function () {
    importStatement($this->lottery, windowRows());

    DB::listen(function (QueryExecuted $query) {
        if (str_contains($query->sql, 'CASE WHEN sold_tickets')) {
            throw new RuntimeException('Simulated failure');
        }
    });

    expect(fn () => app(LotteryInvalidationService::class)->invalidate(
        $this->lottery,
        $this->admin,
        '2026-11-10',
        '2026-11-15',
        'Алдааны үеийн буцаалт.',
        3,
        6,
    ))->toThrow(RuntimeException::class, 'Simulated failure');

    expect($this->lottery->invalidationPeriods()->exists())->toBeFalse()
        ->and($this->lottery->invalidatedTransfers()->exists())->toBeFalse()
        ->and($this->lottery->transactions()->where('is_excluded', true)->exists())->toBeFalse()
        ->and($this->lottery->bankEntries()->where('is_excluded', true)->exists())->toBeFalse()
        ->and($this->lottery->tickets()->count())->toBe(8)
        ->and($this->lottery->fresh()->sold_tickets)->toBe(8);
});

test('bulk invalidation requires an allowlisted admin', function () {
    importStatement($this->lottery, windowRows());
    $this->actingAs(User::factory()->create());

    previewWindow($this->lottery, '2026-11-10', '2026-11-15')->assertForbidden();

    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations", [
        'starts_on' => '2026-11-10',
        'ends_on' => '2026-11-15',
        'note' => 'Эрхгүй хэрэглэгч.',
        'confirm' => 'батлах',
        'expected_transactions' => 3,
        'expected_tickets' => 6,
    ])->assertForbidden();

    $this->put("/admin/lotteries/{$this->lottery->id}/valid-period", [
        'valid_from' => '2026-11-16',
        'valid_until' => '2026-11-30',
    ])->assertForbidden();

    $this->get("/admin/lotteries/{$this->lottery->id}/import")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invalidation.can_manage', false));

    expect($this->lottery->invalidationPeriods()->exists())->toBeFalse()
        ->and($this->lottery->fresh()->valid_from)->toBeNull()
        ->and($this->lottery->tickets()->count())->toBe(8);
});

test('bulk invalidation is closed to everyone when no admin is configured', function () {
    config(['lottery.invalidation_admin_ids' => []]);

    previewWindow($this->lottery, '2026-11-10', '2026-11-15')->assertForbidden();
    $this->put("/admin/lotteries/{$this->lottery->id}/valid-period", ['valid_from' => null, 'valid_until' => null])
        ->assertForbidden();
});

test('guests cannot reach the invalidation endpoints', function () {
    auth()->forgetUser();

    $this->get("/admin/lotteries/{$this->lottery->id}/invalidations/preview?starts_on=2026-11-10&ends_on=2026-11-15")
        ->assertRedirect('/login');
    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations")->assertRedirect('/login');
    $this->put("/admin/lotteries/{$this->lottery->id}/valid-period")->assertRedirect('/login');
});

test('bulk invalidation validates the window, note and confirmation', function (array $overrides, string $field) {
    $this->post("/admin/lotteries/{$this->lottery->id}/invalidations", array_merge([
        'starts_on' => '2026-11-10',
        'ends_on' => '2026-11-15',
        'note' => 'Тохирол явагдсан.',
        'confirm' => 'батлах',
        'expected_transactions' => 0,
        'expected_tickets' => 0,
    ], $overrides))->assertSessionHasErrors($field);

    expect($this->lottery->invalidationPeriods()->exists())->toBeFalse();
})->with([
    'missing start' => [['starts_on' => ''], 'starts_on'],
    'malformed start' => [['starts_on' => '2026/11/10'], 'starts_on'],
    'end before start' => [['ends_on' => '2026-11-09'], 'ends_on'],
    'end in the future' => [['ends_on' => '2026-11-21'], 'ends_on'],
    'missing note' => [['note' => ''], 'note'],
    'too short note' => [['note' => 'abc'], 'note'],
    'wrong confirmation' => [['confirm' => 'тийм'], 'confirm'],
    'missing expected count' => [['expected_transactions' => null], 'expected_transactions'],
]);

test('today is the Ulaanbaatar calendar day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 17:30:00', 'UTC'));

    previewWindow($this->lottery, '2026-11-20', '2026-11-20')->assertOk();
    previewWindow($this->lottery, '2026-11-21', '2026-11-21')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_on' => 'Ирээдүйн өдрийг хүчингүй болгох боломжгүй.']);

    $this->get("/admin/lotteries/{$this->lottery->id}/import")
        ->assertInertia(fn (Assert $page) => $page->where('invalidation.today', '2026-11-20'));
});

test('deleting a lottery also removes its invalidation history', function () {
    importStatement($this->lottery, windowRows());
    closeWindow($this->lottery, '2026-11-10', '2026-11-15');

    $this->delete("/admin/lotteries/{$this->lottery->id}", ['confirm' => 'батлах'])->assertRedirect('/admin/lotteries');

    expect(Lottery::query()->find($this->lottery->id))->toBeNull()
        ->and(LotteryInvalidationPeriod::query()->count())->toBe(0)
        ->and(InvalidatedTransfer::query()->count())->toBe(0);
});
