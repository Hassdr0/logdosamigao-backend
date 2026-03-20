<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Players\Player;
use App\Modules\Raids\Raid;
use App\Modules\Performances\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RankingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_returns_players_ordered_by_avg_dps(): void
    {
        $p1   = Player::factory()->create(['name' => 'Low']);
        $p2   = Player::factory()->create(['name' => 'High']);
        $raid = Raid::factory()->create();

        Performance::factory()->create(['player_id' => $p1->id, 'raid_id' => $raid->id, 'boss_name' => 'Boss1', 'dps_avg' => 100000]);
        Performance::factory()->create(['player_id' => $p2->id, 'raid_id' => $raid->id, 'boss_name' => 'Boss1', 'dps_avg' => 400000]);

        $response = $this->getJson('/api/rankings');
        $response->assertStatus(200);
        $data = $response->json('rankings');
        $this->assertEquals('High', $data[0]['name']);
        $this->assertEquals('Low',  $data[1]['name']);
    }

    public function test_ranking_filters_by_difficulty(): void
    {
        $player = Player::factory()->create();
        $mythic = Raid::factory()->create(['difficulty' => 'mythic']);
        $heroic = Raid::factory()->create(['difficulty' => 'heroic']);

        Performance::factory()->create(['player_id' => $player->id, 'raid_id' => $mythic->id, 'boss_name' => 'B', 'dps_avg' => 300000]);
        Performance::factory()->create(['player_id' => $player->id, 'raid_id' => $heroic->id, 'boss_name' => 'B', 'dps_avg' => 250000]);

        $response = $this->getJson('/api/rankings?difficulty=mythic');
        $response->assertStatus(200);
        $data = $response->json('rankings');
        $this->assertEquals(300000, $data[0]['avg_dps']);
    }
}
