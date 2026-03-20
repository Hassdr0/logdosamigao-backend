<?php
namespace Database\Seeders;

use App\Modules\Players\Player;
use App\Modules\Raids\Raid;
use App\Modules\Performances\Performance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WowSeeder extends Seeder
{
    private array $bosses = [
        "Ulgrax the Devourer",
        "The Bloodbound Horror",
        "Sikran, Captain of the Sureki",
        "Rasha'nan",
        "Eggtender Ovi'nax",
        "Nexus-Princess Ky'veza",
        "The Silken Court",
        "Queen Ansurek",
    ];

    public function run(): void
    {
        $players = [
            ['name' => 'Arthemion', 'realm' => 'azralon', 'region' => 'US', 'class' => 'mage',        'spec' => 'arcane',        'item_level' => 639],
            ['name' => 'Grommash',  'realm' => 'azralon', 'region' => 'US', 'class' => 'warrior',     'spec' => 'fury',          'item_level' => 635],
            ['name' => 'Vorath',    'realm' => 'azralon', 'region' => 'US', 'class' => 'hunter',      'spec' => 'beast-mastery', 'item_level' => 632],
            ['name' => 'Zyndra',    'realm' => 'azralon', 'region' => 'US', 'class' => 'rogue',       'spec' => 'outlaw',        'item_level' => 630],
            ['name' => 'Drakthar',  'realm' => 'azralon', 'region' => 'US', 'class' => 'deathknight', 'spec' => 'unholy',        'item_level' => 628],
        ];

        $playerModels = collect($players)->map(fn($p) => Player::create(array_merge($p, ['is_active' => true])));

        for ($i = 0; $i < 5; $i++) {
            $raid = Raid::create([
                'wcl_report_id' => Str::random(16),
                'instance_name' => "Nerub'ar Palace",
                'difficulty'    => $i < 3 ? 'mythic' : ($i < 4 ? 'heroic' : 'normal'),
                'date'          => now()->subDays($i * 3)->toDateString(),
                'bosses_killed' => $i === 0 ? 8 : rand(5, 8),
                'total_bosses'  => 8,
                'wcl_url'       => 'https://www.warcraftlogs.com/reports/' . Str::random(16),
            ]);

            foreach ($playerModels as $player) {
                foreach ($this->bosses as $boss) {
                    $base = match($player->name) {
                        'Arthemion' => 400000,
                        'Grommash'  => 375000,
                        'Vorath'    => 345000,
                        'Zyndra'    => 320000,
                        default     => 290000,
                    };
                    $dps_best = $base + rand(-20000, 30000);
                    Performance::create([
                        'player_id'    => $player->id,
                        'raid_id'      => $raid->id,
                        'boss_name'    => $boss,
                        'dps_best'     => $dps_best,
                        'dps_avg'      => (int)($dps_best * 0.85),
                        'parse_pct'    => rand(60, 99),
                        'ilvl_at_time' => $player->item_level - rand(0, 5),
                        'spec_at_time' => $player->spec,
                        'kills'        => rand(1, 3),
                    ]);
                }
            }
        }
    }
}
