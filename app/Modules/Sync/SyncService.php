<?php
namespace App\Modules\Sync;

use App\Modules\Players\Player;
use App\Modules\Raids\Raid;
use App\Modules\Performances\Performance;
use App\Modules\SyncLogs\SyncLog;
use Illuminate\Support\Facades\Log;

class SyncService
{
    public function __construct(
        private WarcraftLogsService $wcl
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

            $reports = $this->wcl->getCharacterReports(
                $player->name,
                $player->realm,
                $player->region,
                $startTime
            );

            $existingCodes = Raid::pluck('wcl_report_id')->toArray();

            foreach ($reports as $report) {
                $code = $report['code'];

                if (in_array($code, $existingCodes)) {
                    continue;
                }

                try {
                    $this->importReport($player, $code, $report);
                    $reportsFetched++;
                } catch (\Throwable $e) {
                    $errors[] = "Report {$code}: " . $e->getMessage();
                    Log::warning("SyncService: failed to import report {$code} for {$player->name}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->updatePlayerFromLatestPerformance($player);
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

        $rankings  = $reportData['rankings']['data'] ?? [];
        $zoneName  = $reportData['zone']['name']     ?? 'Unknown';
        $startTime = $reportData['startTime']        ?? (time() * 1000);

        $raid = Raid::updateOrCreate(
            ['wcl_report_id' => $code],
            [
                'instance_name' => $zoneName,
                'difficulty'    => 'mythic',
                'date'          => date('Y-m-d', (int) ($startTime / 1000)),
                'bosses_killed' => count($rankings),
                'total_bosses'  => 8,
                'wcl_url'       => 'https://www.warcraftlogs.com/reports/' . $code,
            ]
        );

        $playerEntry = collect($rankings)->firstWhere('name', $player->name);

        if (!$playerEntry) {
            return;
        }

        $itemLevel = $playerEntry['gear']['itemLevel'] ?? 0;
        $spec      = strtolower($playerEntry['spec']   ?? '');

        foreach ($rankings as $bossRanking) {
            if (($bossRanking['name'] ?? '') !== $player->name) {
                continue;
            }

            $bossName = $bossRanking['boss'] ?? 'Unknown Boss';

            Performance::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'raid_id'   => $raid->id,
                    'boss_name' => $bossName,
                ],
                [
                    'dps_best'     => (int) ($bossRanking['bestAmount'] ?? 0),
                    'dps_avg'      => (int) ($bossRanking['amount']     ?? 0),
                    'parse_pct'    => (int) ($bossRanking['rankPercent'] ?? 0),
                    'ilvl_at_time' => $itemLevel,
                    'spec_at_time' => $spec,
                    'kills'        => 1,
                ]
            );
        }

        if ($itemLevel > 0) {
            $player->item_level = $itemLevel;
        }
        if ($spec) {
            $player->spec = $spec;
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
}
