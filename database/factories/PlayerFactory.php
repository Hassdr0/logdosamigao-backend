<?php
namespace Database\Factories;

use App\Modules\Players\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        $classes = ['mage','warrior','hunter','rogue','deathknight','paladin','priest','shaman','druid','warlock'];
        return [
            'name'       => $this->faker->unique()->userName(),
            'realm'      => 'azralon',
            'region'     => 'US',
            'class'      => $this->faker->randomElement($classes),
            'spec'       => $this->faker->word(),
            'item_level' => $this->faker->numberBetween(600, 650),
            'is_active'  => true,
        ];
    }
}
