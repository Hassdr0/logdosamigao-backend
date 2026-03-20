<?php
namespace Database\Factories;

use App\Modules\Raids\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RaidFactory extends Factory
{
    protected $model = Raid::class;

    public function definition(): array
    {
        return [
            'wcl_report_id' => Str::random(16),
            'instance_name' => "Nerub'ar Palace",
            'difficulty'    => $this->faker->randomElement(['mythic','heroic','normal']),
            'date'          => $this->faker->dateTimeBetween('-3 months'),
            'bosses_killed' => $this->faker->numberBetween(1, 8),
            'total_bosses'  => 8,
            'wcl_url'       => 'https://www.warcraftlogs.com/reports/' . Str::random(16),
        ];
    }
}
