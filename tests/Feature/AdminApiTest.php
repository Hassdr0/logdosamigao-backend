<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Players\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAndGetToken(): string
    {
        config(['app.admin_password' => Hash::make('secret123')]);
        $response = $this->postJson('/api/admin/login', ['password' => 'secret123']);
        $response->assertStatus(200);
        return $response->json('token');
    }

    public function test_login_with_correct_password_returns_token(): void
    {
        config(['app.admin_password' => Hash::make('secret123')]);
        $response = $this->postJson('/api/admin/login', ['password' => 'secret123']);
        $response->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        config(['app.admin_password' => Hash::make('secret123')]);
        $response = $this->postJson('/api/admin/login', ['password' => 'wrong']);
        $response->assertStatus(401);
    }

    public function test_admin_route_requires_token(): void
    {
        $response = $this->getJson('/api/admin/players');
        $response->assertStatus(401);
    }

    public function test_admin_can_create_player(): void
    {
        $token = $this->loginAndGetToken();
        $response = $this->postJson('/api/admin/players', [
            'name' => 'Arthemion', 'realm' => 'azralon', 'region' => 'US',
        ], ['Authorization' => "Bearer $token"]);
        $response->assertStatus(201)->assertJsonPath('player.name', 'Arthemion');
        $this->assertDatabaseHas('players', ['name' => 'Arthemion']);
    }

    public function test_admin_can_delete_player(): void
    {
        $token  = $this->loginAndGetToken();
        $player = Player::factory()->create();
        $response = $this->deleteJson("/api/admin/players/{$player->id}", [], ['Authorization' => "Bearer $token"]);
        $response->assertStatus(200);
        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_admin_sync_all_returns_results(): void
    {
        $token = $this->loginAndGetToken();

        $response = $this->postJson('/api/admin/sync', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'results'])
                 ->assertJsonPath('results.total', 0);
    }

    public function test_admin_sync_player_returns_sync_status(): void
    {
        $token  = $this->loginAndGetToken();
        $player = \App\Modules\Players\Player::factory()->create(['name' => 'Arthemion', 'realm' => 'azralon', 'region' => 'US']);

        \Illuminate\Support\Facades\Http::fake([
            'www.warcraftlogs.com/oauth/token' => \Illuminate\Support\Facades\Http::response([
                'access_token' => 'fake-token',
            ], 200),
            'www.warcraftlogs.com/api/v2/client' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    'characterData' => [
                        'character' => [
                            'recentReports' => ['data' => []],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson("/api/admin/sync/{$player->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'status', 'reports', 'errors'])
                 ->assertJsonPath('status', 'success');
    }

    public function test_admin_sync_logs_returns_list(): void
    {
        $token = $this->loginAndGetToken();

        $response = $this->getJson('/api/admin/sync/logs', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['logs']);
    }

    public function test_admin_sync_requires_token(): void
    {
        $response = $this->postJson('/api/admin/sync');
        $response->assertStatus(401);
    }
}
