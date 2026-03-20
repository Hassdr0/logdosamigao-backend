<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Players\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlayerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_players_returns_list(): void
    {
        Player::factory()->count(3)->create();
        $response = $this->getJson('/api/players');
        $response->assertStatus(200)->assertJsonCount(3, 'players');
    }

    public function test_get_player_by_realm_and_name(): void
    {
        Player::factory()->create(['name' => 'Arthemion', 'realm' => 'azralon', 'region' => 'US']);
        $response = $this->getJson('/api/players/azralon/Arthemion');
        $response->assertStatus(200)->assertJsonPath('player.name', 'Arthemion');
    }

    public function test_get_nonexistent_player_returns_404(): void
    {
        $response = $this->getJson('/api/players/azralon/Ninguem');
        $response->assertStatus(404);
    }
}
