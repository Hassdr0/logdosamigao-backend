<?php
namespace App\Modules\Sync;

use App\Modules\Players\Player;
use App\Modules\Raids\Raid;
use App\Modules\Performances\Performance;
use App\Modules\Dungeons\DungeonRun;
use App\Modules\SyncLogs\SyncLog;
use Illuminate\Support\Facades\Log;

class SyncService
{
    public function __construct(
        private WarcraftLogsService $wcl,
        private BlizzardService $blizzard
    ) {}

    public function syncAll(): array
    {
        $players = Player::where('is_active', true)->get();
        $results = ['success' => 0, 'failed' => 0, 'total' => $players->count()];

        foreach ($players as $player) {
            $log = $this->syncPlayer($player);
            $log->status === 'failed' ? $results['failed']++ : $results['success']++;
        }

        return $results;
    }

    public function syncPlayer(Player $player): SyncLog
    {
        $reportsFetched = 0;
        $errors         = [];

        try {
            $startTime = $player->last_synced_at
                ? (float) ($player->last_synced_at->timestamp * 1000)
                : (float) (now()->subDays(90)->timestamp * 1000);

            $charData = $this->wcl->getCharacterReports(
                $player->name,
                $player->realm,
                $player->region,
                $startTime
            );
            $reports   = $charData['reports']    ?? [];
            $gearScore = (int) ($charData['gear_score'] ?? 0);

            // Atualiza ilvl real do personagem se disponível
            if ($gearScore > 0) {
                $player->item_level = $gearScore;
            }

            // Códigos já processados: guardados no error_message do sync_log como JSON, ou como wcl_report_id das raids
            $processedCodes = \Illuminate\Support\Facades\DB::table('sync_logs')
                ->where('player_id', $player->id)
                ->whereNotNull('error_message')
                ->where('error_message', 'like', '["wcl:%')
                ->pluck('error_message')
                ->flatMap(fn($j) => json_decode($j, true) ?? [])
                ->toArray();
            // Fallback: qualquer código que já esteja como wcl_report_id em alguma raid
            $existingCodes = array_unique(array_merge(
                $processedCodes,
                Raid::pluck('wcl_report_id')->toArray()
            ));

            foreach ($reports as $report) {
                $code = $report['code'];

                if (in_array($code, $existingCodes)) {
                    continue;
                }

                try {
                    $this->importReport($player, $code, $report);
                    $reportsFetched++;
                    usleep(300000); // 300ms entre requests para evitar 429
                } catch (\Throwable $e) {
                    $errors[] = "Report {$code}: " . $e->getMessage();
                    Log::warning("SyncService: failed to import report {$code} for {$player->name}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->updatePlayerFromLatestPerformance($player);
            $this->syncMythicPlus($player);

            // Atualiza ilvl real e avatar via API da Blizzard
            $blizzData = $this->blizzard->getCharacterData($player->name, $player->realm, $player->region);
            if ($blizzData) {
                if (!empty($blizzData['item_level'])) {
                    $player->item_level = $blizzData['item_level'];
                }
                if (!empty($blizzData['avatar_url'])) {
                    $player->avatar_url = $blizzData['avatar_url'];
                }
            }

            $player->last_synced_at = now();
            $player->save();

            $status = empty($errors) ? 'success' : 'partial';

        } catch (\Throwable $e) {
            $status = 'failed';
            $errors[] = $e->getMessage();
            Log::error("SyncService: full failure for player {$player->name}", [
                'error' => $e->getMessage(),
            ]);
        }

        return SyncLog::create([
            'player_id'      => $player->id,
            'status'         => $status,
            'reports_fetched'=> $reportsFetched,
            'error_message'  => empty($errors) ? null : implode("\n", $errors),
            'synced_at'      => now(),
        ]);
    }

    private function importReport(Player $player, string $code, array $reportMeta): void
    {
        $reportData = $this->wcl->getReportRankings($code);

        if (empty($reportData)) {
            throw new \RuntimeException("Empty response for report {$code}");
        }

        $bosses    = $reportData['rankings']['data'] ?? [];
        $startTime = $reportData['startTime']        ?? (time() * 1000);

        // Carrega mapeamento da season atual via config
        $season = config('wcl.current_season', 'midnight_s1');
        $encounterToInstance = config("wcl.seasons.{$season}.raid_encounters", []);
        $totalBossesMap      = config("wcl.seasons.{$season}.raid_total_bosses", []);

        // Filtrar apenas bosses de raid conhecidos (ignorar M+ e outros)
        $raidBosses = array_filter($bosses, fn($b) => isset($encounterToInstance[$b['encounter']['id'] ?? 0]));
        $raidBosses = array_values($raidBosses);

        if (empty($raidBosses)) {
            return; // Report só de M+ ou raids desconhecidas
        }

        // WCL difficulty IDs: 1=LFR, 3=Normal, 4=Heroic, 5=Mythic
        $difficultyMap = [1 => 'lfr', 3 => 'normal', 4 => 'heroic', 5 => 'mythic'];
        $date = date('Y-m-d', (int) ($startTime / 1000));

        // Cache de raids por instância+dificuldade para evitar queries repetidas
        $raidCache = [];

        $spec      = '';
        $itemLevel = 0;
        $found     = false;

        foreach ($raidBosses as $bossData) {
            $bossName    = $bossData['encounter']['name'] ?? 'Unknown Boss';
            $encounterId = $bossData['encounter']['id']  ?? 0;
            $zoneName    = $encounterToInstance[$encounterId];
            $difficultyId = $bossData['difficulty'] ?? 4;
            $difficulty   = $difficultyMap[$difficultyId] ?? 'normal';

            // Agrupar por instância + dificuldade + dia (múltiplos reports da mesma noite = 1 raid)
            $raidKey = "{$zoneName}|{$difficulty}|{$date}";
            if (!isset($raidCache[$raidKey])) {
                $raidCache[$raidKey] = Raid::firstOrCreate(
                    [
                        'instance_name' => $zoneName,
                        'difficulty'    => $difficulty,
                        'date'          => $date,
                    ],
                    [
                        'wcl_report_id' => $code,
                        'bosses_killed' => 0,
                        'total_bosses'  => $totalBossesMap[$zoneName] ?? count($encounterToInstance),
                        'wcl_url'       => 'https://www.warcraftlogs.com/reports/' . $code,
                    ]
                );
            }
            $raid = $raidCache[$raidKey];

            // Tentar encontrar player em DPS ou healers
            $dpsPlayers    = $bossData['roles']['dps']['characters']     ?? [];
            $healerPlayers = $bossData['roles']['healers']['characters'] ?? [];

            $playerEntry = collect($dpsPlayers)
                ->first(fn($p) => strtolower($p['name']) === strtolower($player->name));

            $isHealer = false;
            if (!$playerEntry) {
                $playerEntry = collect($healerPlayers)
                    ->first(fn($p) => strtolower($p['name']) === strtolower($player->name));
                $isHealer = (bool) $playerEntry;
            }

            if (!$playerEntry) {
                continue;
            }

            $found     = true;
            $spec      = strtolower($playerEntry['spec'] ?? $spec);
            $itemLevel = (int) ($playerEntry['bracketData'] ?? $itemLevel);

            $amount   = (int) ($playerEntry['amount'] ?? 0);
            $newParse = (int) ($playerEntry['rankPercent'] ?? 0);
            $newDps   = $isHealer ? 0 : $amount;
            $newHps   = $isHealer ? $amount : 0;

            $existing = Performance::where([
                'player_id' => $player->id,
                'raid_id'   => $raid->id,
                'boss_name' => $bossName,
            ])->first();

            if ($existing) {
                // Média incremental correta: avg = avg + (new - avg) / count
                $newCount = $existing->kill_count + 1;
                if ($isHealer) {
                    $existing->hps = (int) round($existing->hps + ($newHps - $existing->hps) / $newCount);
                } else {
                    $existing->dps_avg  = (int) round($existing->dps_avg + ($newDps - $existing->dps_avg) / $newCount);
                    $existing->dps_best = max($existing->dps_best, $newDps);
                }
                $existing->parse_pct    = max($existing->parse_pct, $newParse);
                $existing->ilvl_at_time = max($existing->ilvl_at_time, $itemLevel);
                $existing->spec_at_time = $spec ?: $existing->spec_at_time;
                $existing->kill_count   = $newCount;
                $existing->save();
            } else {
                Performance::create([
                    'player_id'    => $player->id,
                    'raid_id'      => $raid->id,
                    'boss_name'    => $bossName,
                    'dps_best'     => $newDps,
                    'dps_avg'      => $newDps,
                    'hps'          => $newHps,
                    'parse_pct'    => $newParse,
                    'ilvl_at_time' => $itemLevel,
                    'spec_at_time' => $spec,
                    'kills'        => 1,
                    'kill_count'   => 1,
                ]);
            }

            // Atualiza bosses_killed da raid agrupada
            $raid->bosses_killed = Performance::where('raid_id', $raid->id)
                ->where('player_id', $player->id)->count();
            $raid->save();
        }

        if (!$found) {
            return;
        }

        if ($spec) {
            $player->spec = $spec;
        }
        if ($itemLevel > 0) {
            $player->item_level = $itemLevel;
        }

        // Atualizar classe do player
        $dpsPlayers  = $bosses[0]['roles']['dps']['characters'] ?? [];
        $playerEntry = collect($dpsPlayers)
            ->first(fn($p) => strtolower($p['name']) === strtolower($player->name));
        if ($playerEntry && !empty($playerEntry['class'])) {
            $player->class = strtolower($playerEntry['class']);
        }
    }

    private function updatePlayerFromLatestPerformance(Player $player): void
    {
        $latest = Performance::where('player_id', $player->id)
            ->whereNotNull('ilvl_at_time')
            ->where('ilvl_at_time', '>', 0)
            ->orderByDesc('updated_at')
            ->first();

        if ($latest) {
            if ($latest->ilvl_at_time > 0) {
                $player->item_level = $latest->ilvl_at_time;
            }
            if ($latest->spec_at_time) {
                $player->spec = $latest->spec_at_time;
            }
        }
    }

    public function syncMythicPlusOnly(Player $player): int
    {
        $saved = $this->doSyncMythicPlus($player);
        $player->last_synced_at = now();
        $player->save();
        return $saved;
    }

    private function syncMythicPlus(Player $player): void
    {
        try {
            $this->doSyncMythicPlus($player);
        } catch (\Throwable $e) {
            Log::warning("SyncService: M+ sync failed for {$player->name}: " . $e->getMessage());
        }
    }

    private function doSyncMythicPlus(Player $player): int
    {
        $data = $this->wcl->getMythicPlusRankings($player->name, $player->realm, $player->region);
        $rankings = $data['rankings'] ?? [];
        $saved = 0;

        foreach ($rankings as $rk) {
            $dungeonName = $rk['encounter']['name'] ?? null;
            $totalRuns   = (int) ($rk['totalKills'] ?? 0);
            if (!$dungeonName || $totalRuns === 0) continue;

            $keyLevel      = (int)   ($rk['bestRank']['ilvl']         ?? 0);
            $score         = (float) ($rk['bestAmount']                ?? 0);
            $parse         = (float) ($rk['rankPercent']               ?? 0);
            $medianPercent = (float) ($rk['medianPercent']             ?? 0);
            $bestDps       = (int)   ($rk['bestRank']['totalDamage']   ?? 0);
            $spec          = strtolower($rk['spec']                    ?? '');
            $serverRank    = (int)   ($rk['allStars']['serverRank']    ?? 0);
            $regionRank    = (int)   ($rk['allStars']['regionRank']    ?? 0);
            $worldRank     = (int)   ($rk['allStars']['rank']          ?? 0);
            $bestTimeMs    = (int)   ($rk['bestRank']['duration']      ?? 0);

            DungeonRun::updateOrCreate(
                ['player_id' => $player->id, 'dungeon_name' => $dungeonName],
                [
                    'key_level'      => $keyLevel,
                    'score'          => $score,
                    'rank_percent'   => $parse,
                    'median_percent' => $medianPercent,
                    'best_dps'       => $bestDps,
                    'total_runs'     => $totalRuns,
                    'spec'           => $spec,
                    'server_rank'    => $serverRank,
                    'region_rank'    => $regionRank,
                    'world_rank'     => $worldRank,
                    'best_time_ms'   => $bestTimeMs,
                ]
            );
            $saved++;
        }

        return $saved;
    }
}
