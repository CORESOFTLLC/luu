<?php

namespace Database\Factories;

use App\Models\Lottery;
use App\Models\LotteryInvalidationPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotteryInvalidationPeriod>
 */
class LotteryInvalidationPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-60 days', '-30 days');

        return [
            'lottery_id' => Lottery::factory(),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => (clone $startsOn)->modify('+7 days')->format('Y-m-d'),
            'note' => fake()->sentence(),
            'user_id' => null,
            'user_name' => fake()->name(),
            'transactions_count' => 0,
            'tickets_count' => 0,
            'amount_total' => 0,
        ];
    }
}
