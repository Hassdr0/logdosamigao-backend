<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Players\Player;
use App\Modules\Raids\Raid;
use App\Modules\Performances\Performance;
use App\Modules\SyncLogs\SyncLog;
use App\Modules\Sync\SyncService;
use App\Modules\Sync\WarcraftLogsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function mockTokenResponse(): void
    {
        Http::fake([
            'www.warcraftlogs.com/oauth/token' => Http::response([
                'access_token' => 'fake-token-abc123',
                'token_type'   => 'Bearer',
                'expires_in'   => 82800,
            ], 200),
        ]);
    }

    private function fakeWclResponses(string $playerName, string $reportCode = 'ABCD1234567890AB'): void
    {
        Http::fake([
            'www.warcraftlogs.com/oauth/token' => Http::response([
                'access_token' => 'fake-token-abc123',
                'token_type'   => 'Bearer',
                'expires_in'   => 82800,
            ], 200),
            'www.warcraftlogs.com/api/v2/client' => Http::sequence()
                ->push([
                    'data' => [
                        'characterData' => [
                            'character' => [
                                'recentReports' => [
                                    'data' => [
                                        [
                                            'code'      => $reportCode,
                                            'startTime' => 1700000000000.0,
                                            'title'     => "Mythic Night",
                                            'zone'      => ['name' => "Nerub'ar Palace"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'reportData' => [
                            'report' => [
                                'startTime' => 1700000000000.0,
                                'zone'      => ['name' => "Nerub'ar Palace"],
                                'rankings'  => [
                                    'data' => [
                                        [
                                            'name'        => $playerName,
                                            'boss'        => 'Ulgrax the Devourer',
                                            'class'       => 'Mage',
                                            'spec'        => 'Arcane',
                                            'rankPercent' => 95.5,
                                            'amount'      => 380000,
                                            'bestAmount'  => 420000,
                                            'gear'        => ['itemLevel' => 639],
                                        ],
                                        [
                                            'name'        => $playerName,
                                            'boss'        => 'Queen Ansurek',
                                            'class'       => 'Mage',
                                            'spec'        => 'Arcane',
                                            'rankPercent' => 88.0,
                                            'amount'      => 355000,
                                            'bestAmount'  => 400000,
                                            'gear'        => ['itemLevel' => 639],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);
    }

    public function test_get_token_fetches_and_caches_oauth_token(): void
    {
        Cache::flush();
        $this->mockTokenResponse();

        $service = app(WarcraftLogsService::class);
        $token   = $service->getToken();

        $this->assertEquals('fake-token-abc123', $token);
        $this->assertEquals('fake-token-abc123', Cache::get('wcl_oauth_token'));
    }

    public function test_get_token_uses_cached_value_without_http_call(): void
    {
        Cache::put('wcl_oauth_token', 'cached-token-xyz', 82800);

        Http::fake([]);

        $service = app(WarcraftLogsService::class);
        $token   = $service->getToken();

        $this->assertEquals('cached-token-xyz', $token);
        Http::assertNothingSent();
    }

    public function test_get_character_reports_returns_array(): void
    {
        Cache::put('wcl_oauth_token', 'fake-token', 82800);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client' => Http::response([
                'data' => [
                    'characterData' => [
                        'character' => [
                            'recentReports' => [
                                'data' => [
                                    ['code' => 'ABC123', 'startTime' => 1700000000000.0, 'title' => 'Test', 'zone' => ['name' => "Nerub'ar Palace"]],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(WarcraftLogsService::class);
        $reports = $service->getCharacterReports('Arthemion', 'azralon', 'US', 1699000000000.0);

        $this->assertCount(1, $reports);
        $this->assertEquals('ABC123', $reports[0]['code']);
    }

    public function test_graphql_error_throws_runtime_exception(): void
    {
        Cache::put('wcl_oauth_token', 'fake-token', 82800);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client' => Http::response([
                'errors' => [['message' => 'Character not found']],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Character not found/');

        $service = app(WarcraftLogsService::class);
        $service->getCharacterReports('Unknown', 'azralon', 'US', 0.0);
    }

    public function test_http_failure_throws_runtime_exception(): void
    {
        Cache::put('wcl_oauth_token', 'fake-token', 82800);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client' => Http::response([], 503),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/503/');

        $service = app(WarcraftLogsService::class);
        $service->getCharacterReports('Arthemion', 'azralon', 'US', 0.0);
    }

    public function test_sync_player_creates_raid_and_performances(): void
    {
        $player = Player::factory()->create([
            'name'           => 'Arthemion',
            'realm'          => 'azralon',
            'region'         => 'US',
            'last_synced_at' => null,
        ]);

        $this->fakeWclResponses('Arthemion');

        $service = app(SyncService::class);
        $syncLog = $service->syncPlayer($player);

        $this->assertDatabaseHas('raids', ['wcl_report_id' => 'ABCD1234567890AB']);

        $this->assertEquals(2, Performance::where('player_id', $player->id)->count());
        $this->assertDatabaseHas('performances', [
            'player_id'  => $player->id,
            'boss_name'  => 'Ulgrax the Devourer',
            'dps_best'   => 420000,
            'dps_avg'    => 380000,
            'parse_pct'  => 95,
        ]);

        $this->assertEquals('success', $syncLog->status);
        $this->assertEquals(1, $syncLog->reports_fetched);
        $this->assertNull($syncLog->error_message);
    }

    public function test_sync_player_updates_player_item_level_and_spec(): void
    {
        $player = Player::factory()->create([
            'name'       => 'Arthemion',
            'realm'      => 'azralon',
            'region'     => 'US',
            'item_level' => 600,
            'spec'       => 'fire',
        ]);

        $this->fakeWclResponses('Arthemion');

        $service = app(SyncService::class);
        $service->syncPlayer($player);

        $player->refresh();
        $this->assertEquals(639, $player->item_level);
        $this->assertEquals('arcane', $player->spec);
    }

    public function test_sync_player_updates_last_synced_at(): void
    {
        $player = Player::factory()->create([
            'name'           => 'Arthemion',
            'realm'          => 'azralon',
            'region'         => 'US',
            'last_synced_at' => null,
        ]);

        $this->fakeWclResponses('Arthemion');

        $service = app(SyncService::class);
        $service->syncPlayer($player);

        $player->refresh();
        $this->assertNotNull($player->last_synced_at);
        $this->assertTrue($player->last_synced_at->isToday());
    }

    public function test_sync_skips_already_imported_report(): void
    {
        $player = Player::factory()->create([
            'name'   => 'Arthemion',
            'realm'  => 'azralon',
            'region' => 'US',
        ]);

        Raid::factory()->create(['wcl_report_id' => 'ABCD1234567890AB']);

        $this->fakeWclResponses('Arthemion');

        $service = app(SyncService::class);
        $syncLog = $service->syncPlayer($player);

        $this->assertEquals(0, $syncLog->reports_fetched);
        $this->assertEquals('success', $syncLog->status);
    }

    public function test_sync_player_not_in_rankings_creates_no_performances(): void
    {
        $player = Player::factory()->create([
            'name'   => 'Outsider',
            'realm'  => 'azralon',
            'region' => 'US',
        ]);

        $this->fakeWclResponses('Arthemion');

        $service = app(SyncService::class);
        $syncLog = $service->syncPlayer($player);

        $this->assertEquals(0, Performance::where('player_id', $player->id)->count());
        $this->assertEquals(1, $syncLog->reports_fetched);
    }

    public function test_sync_player_on_wcl_api_failure_returns_failed_log(): void
    {
        $player = Player::factory()->create([
            'name'   => 'Arthemion',
            'realm'  => 'azralon',
            'region' => 'US',
        ]);

        Http::fake([
            'www.warcraftlogs.com/oauth/token' => Http::response([
                'access_token' => 'fake-token',
            ], 200),
            'www.warcraftlogs.com/api/v2/client' => Http::response([], 500),
        ]);

        $service = app(SyncService::class);
        $syncLog = $service->syncPlayer($player);

        $this->assertEquals('failed', $syncLog->status);
        $this->assertNotNull($syncLog->error_message);
    }

    public function test_sync_all_runs_for_all_active_players(): void
    {
        $p1 = Player::factory()->create(['name' => 'Alpha', 'realm' => 'azralon', 'region' => 'US', 'is_active' => true]);
        $p2 = Player::factory()->create(['name' => 'Beta',  'realm' => 'azralon', 'region' => 'US', 'is_active' => true]);
        Player::factory()->create(['is_active' => false]);

        Http::fake([
            'www.warcraftlogs.com/oauth/token' => Http::response(['access_token' => 'tok'], 200),
            'www.warcraftlogs.com/api/v2/client' => Http::response([
                'data' => [
                    'characterData' => [
                        'character' => [
                            'recentReports' => ['data' => []],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(SyncService::class);
        $results = $service->syncAll();

        $this->assertEquals(2, $results['total']);
        $this->assertEquals(2, SyncLog::count());
    }

    public function test_sync_partial_status_when_some_reports_fail(): void
    {
        $player = Player::factory()->create([
            'name'   => 'Arthemion',
            'realm'  => 'azralon',
            'region' => 'US',
        ]);

        Cache::put('wcl_oauth_token', 'fake-token', 82800);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client' => Http::sequence()
                ->push([
                    'data' => [
                        'characterData' => [
                            'character' => [
                                'recentReports' => [
                                    'data' => [
                                        ['code' => 'FAILREPORT12345X', 'startTime' => 1700000000000.0, 'title' => 'Test', 'zone' => ['name' => 'Zone']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([], 503),
        ]);

        $service = app(SyncService::class);
        $syncLog = $service->syncPlayer($player);

        $this->assertEquals('partial', $syncLog->status);
        $this->assertStringContainsString('FAILREPORT12345X', $syncLog->error_message);
    }
}
