<?php

use App\Models\Lottery;
use App\Models\LotteryInvalidationPeriod;
use App\Models\User;
use App\Services\LotteryInvalidationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * These tests need committed rows that a second connection can lock, so they manage the
 * schema themselves instead of running inside a rolled-back transaction. They only run
 * against MySQL; SQLite has no row locks to exercise.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Row locks are only exercised against MySQL.');
    }

    $this->artisan('migrate:fresh');
    $this->travelTo(CarbonImmutable::parse('2026-11-20 04:00:00', 'UTC'));

    $this->admin = User::factory()->create();
    config(['lottery.invalidation_admin_ids' => [$this->admin->id]]);

    $this->lottery = Lottery::factory()->active()->create([
        'price_per_ticket' => 15000,
        'total_tickets' => null,
        'max_tickets_per_transaction' => null,
    ]);

    importStatement($this->lottery, [
        ['2026-11-12 10:00:00', 15000, '99000061 сугалаа'],
        ['2026-11-13 10:00:00', 30000, '99000062 сугалаа'],
    ]);

    config(['database.connections.mysql_concurrent' => config('database.connections.mysql')]);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $this->concurrent = DB::connection('mysql_concurrent');
    $this->concurrent->beginTransaction();
    $this->concurrent->table('lotteries')->where('id', $this->lottery->id)->lockForUpdate()->first();
});

afterEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        return;
    }

    DB::disconnect('mysql_concurrent');
    $this->artisan('migrate:fresh');
});

test('bulk invalidation waits for a lottery that another writer has locked', function () {
    $invalidate = fn () => app(LotteryInvalidationService::class)->invalidate(
        $this->lottery,
        $this->admin,
        '2026-11-10',
        '2026-11-15',
        'Зэрэг ажиллах үеийн шалгалт.',
        2,
        3,
    );

    expect($invalidate)->toThrow(QueryException::class, 'Lock wait timeout');

    expect(LotteryInvalidationPeriod::query()->exists())->toBeFalse()
        ->and($this->lottery->transactions()->where('is_excluded', true)->exists())->toBeFalse()
        ->and($this->lottery->tickets()->count())->toBe(3);

    $this->concurrent->rollBack();
    $invalidate();

    expect(LotteryInvalidationPeriod::query()->count())->toBe(1)
        ->and($this->lottery->tickets()->exists())->toBeFalse();
});

test('an import waits for a lottery that a bulk invalidation has locked', function () {
    $import = fn () => importStatement($this->lottery, [['2026-11-14 10:00:00', 15000, '99000063 сугалаа']]);

    expect($import)->toThrow(QueryException::class, 'Lock wait timeout');

    expect($this->lottery->transactions()->count())->toBe(2)
        ->and($this->lottery->tickets()->count())->toBe(3);

    $this->concurrent->rollBack();

    expect($import())->toMatchArray(['imported' => 1, 'tickets_created' => 1]);
});
