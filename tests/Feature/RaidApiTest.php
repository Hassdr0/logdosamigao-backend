<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Raids\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RaidApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_raids_returns_list(): void
    {
        Raid::factory()->count(5)->create();
        $response = $this->getJson('/api/raids');
        $response->assertStatus(200)->assertJsonCount(5, 'raids');
    }

    public function test_get_raid_by_id(): void
    {
        $raid = Raid::factory()->create();
        $response = $this->getJson("/api/raids/{$raid->id}");
        $response->assertStatus(200)->assertJsonPath('raid.id', $raid->id);
    }

    public function test_get_raids_filter_by_difficulty(): void
    {
        Raid::factory()->create(['difficulty' => 'mythic']);
        Raid::factory()->create(['difficulty' => 'heroic']);
        $response = $this->getJson('/api/raids?difficulty=mythic');
        $response->assertStatus(200)->assertJsonCount(1, 'raids');
    }
}
