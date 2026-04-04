<?php
namespace App\Modules\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RaiderIOService
{
    private const BASE_URL = 'https://raider.io/api/v1';

    // BR realms usam region=us na API do Raider.IO
    private const REGION_MAP = ['BR' => 'us', 'US' => 'us', 'EU' => 'eu', 'KR' => 'kr', 'TW' => 'tw'];

    public function getScores(string $name, string $realm, string $region): ?array
    {
        $region = self::REGION_MAP[strtoupper($region)] ?? 'us';
        $realm  = strtolower(str_replace(' ', '-', $realm));

        try {
            $response = Http::timeout(10)->get(self::BASE_URL . '/characters/profile', [
                'region' => $region,
                'realm'  => $realm,
                'name'   => $name,
                'fields' => 'mythic_plus_scores_by_season:current',
            ]);

            if (!$response->ok()) {
                Log::info("RaiderIO: {$name}-{$realm} not found (HTTP {$response->status()})");
                return null;
            }

            $data    = $response->json();
            $season  = $data['mythic_plus_scores_by_season'][0] ?? null;

            if (!$season) {
                return null;
            }

            $scores   = $season['scores']   ?? [];
            $segments = $season['segments'] ?? [];

            // Score geral e cor
            $allScore = (float) ($scores['all'] ?? 0);
            $allColor = $segments['all']['color'] ?? '#ffffff';

            // Score por spec (spec_0..spec_3) com nome e cor
            $bySpec = [];
            foreach (['spec_0', 'spec_1', 'spec_2', 'spec_3'] as $key) {
                $score = (float) ($scores[$key] ?? 0);
                if ($score > 0) {
                    $bySpec[$key] = [
                        'score' => $score,
                        'color' => $segments[$key]['color'] ?? '#ffffff',
                    ];
                }
            }

            // Scores por role
            foreach (['dps', 'healer', 'tank'] as $role) {
                $score = (float) ($scores[$role] ?? 0);
                if ($score > 0) {
                    $bySpec[$role] = [
                        'score' => $score,
                        'color' => $segments[$role]['color'] ?? '#ffffff',
                    ];
                }
            }

            return [
                'score'          => $allScore,
                'color'          => $allColor,
                'scores_by_spec' => $bySpec,
            ];
        } catch (\Throwable $e) {
            Log::warning("RaiderIO: falha ao buscar scores para {$name}: " . $e->getMessage());
            return null;
        }
    }
}
