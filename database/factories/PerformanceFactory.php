<?php
namespace Database\Factories;

use App\Modules\Performances\Performance;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceFactory extends Factory
{
    protected $model = Performance::class;

    public function definition(): array
    {
        return [
            'player_id'    => \App\Modules\Players\Player::factory(),
            'raid_id'      => \App\Modules\Raids\Raid::factory(),
            'boss_name'    => $this->faker->randomElement(['Ulgrax', 'Bloodbound Horror', 'Queen Ansurek']),
            'dps_best'     => $this->faker->numberBetween(200000, 500000),
            'dps_avg'      => $this->faker->numberBetween(150000, 450000),
            'hps'          => 0,
            'parse_pct'    => $this->faker->numberBetween(1, 100),
            'ilvl_at_time' => $this->faker->numberBetween(610, 645),
            'spec_at_time' => 'arcane',
            'kills'        => $this->faker->numberBetween(1, 5),
        ];
    }
}
