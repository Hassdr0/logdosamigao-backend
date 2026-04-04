<?php
namespace App\Modules\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlizzardService
{
    private const TOKEN_CACHE_KEY = 'blizzard_oauth_token';

    // Região BR usa namespace 'dynamic-us' e locale pt_BR
    private const REGION_NAMESPACE = [
        'us' => 'us',
        'br' => 'us', // BR usa API americana
        'eu' => 'eu',
        'kr' => 'kr',
        'tw' => 'tw',
    ];

    private const REGION_HOST = [
        'us' => 'https://us.api.blizzard.com',
        'br' => 'https://us.api.blizzard.com',
        'eu' => 'https://eu.api.blizzard.com',
        'kr' => 'https://kr.api.blizzard.com',
        'tw' => 'https://tw.api.blizzard.com',
    ];

    public function getToken(): string
    {
        return Cache::remember(
            self::TOKEN_CACHE_KEY,
            82800,
            fn() => $this->fetchToken()
        );
    }

    private function fetchToken(): string
    {
        $response = Http::withBasicAuth(
            config('blizzard.client_id'),
            config('blizzard.client_secret')
        )->asForm()->post('https://oauth.battle.net/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Blizzard OAuth failed: ' . $response->status());
        }

        return $response->json('access_token');
    }

    /**
     * Busca ilvl real e avatar do personagem via API da Blizzard.
     * Retorna ['item_level' => int, 'avatar_url' => string] ou null em caso de falha.
     */
    public function getCharacterData(string $name, string $realm, string $region): ?array
    {
        $region    = strtolower($region);
        $namespace = 'dynamic-' . (self::REGION_NAMESPACE[$region] ?? 'us');
        $host      = self::REGION_HOST[$region] ?? self::REGION_HOST['us'];
        $realmSlug = strtolower(str_replace("'", '', str_replace(' ', '-', $realm)));
        $nameLower = strtolower($name);

        try {
            $token = $this->getToken();

            // Endpoint de equipment para ilvl real
            $equipment = Http::withToken($token)
                ->get("{$host}/profile/wow/character/{$realmSlug}/{$nameLower}/equipment", [
                    'namespace' => $namespace,
                    'locale'    => 'pt_BR',
                ]);

            $itemLevel = null;
            if ($equipment->successful()) {
                $itemLevel = (int) ($equipment->json('equipped_item_level') ?? 0) ?: null;
            }

            // Endpoint de media para avatar
            $media = Http::withToken($token)
                ->get("{$host}/profile/wow/character/{$realmSlug}/{$nameLower}/character-media", [
                    'namespace' => $namespace,
                    'locale'    => 'pt_BR',
                ]);

            $avatarUrl = null;
            if ($media->successful()) {
                $assets = $media->json('assets') ?? [];
                foreach ($assets as $asset) {
                    if (($asset['key'] ?? '') === 'avatar') {
                        $avatarUrl = $asset['value'] ?? null;
                        break;
                    }
                }
            }

            // Spec ativa via specializations
            $specRes = Http::withToken($token)
                ->get("{$host}/profile/wow/character/{$realmSlug}/{$nameLower}/specializations", [
                    'namespace' => $namespace,
                    'locale'    => 'pt_BR',
                ]);
            $activeSpec = null;
            if ($specRes->successful()) {
                $activeSpec = $specRes->json('active_specialization.name') ?? null;
            }

            if (!$itemLevel && !$avatarUrl && !$activeSpec) {
                return null;
            }

            return array_filter([
                'item_level' => $itemLevel,
                'avatar_url' => $avatarUrl,
                'spec'       => $activeSpec ? strtolower($activeSpec) : null,
            ]);

        } catch (\Throwable $e) {
            Log::warning("BlizzardService: falha ao buscar {$name}-{$realm}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca runs de keystone da season atual.
     * Retorna array de runs: [dungeon_name, key_level, duration_ms, timed, completed_at, week]
     */
    public function getKeystoneRuns(string $name, string $realm, string $region, int $seasonId = 1): array
    {
        $region    = strtolower($region);
        $namespace = 'dynamic-' . (self::REGION_NAMESPACE[$region] ?? 'us');
        $host      = self::REGION_HOST[$region] ?? self::REGION_HOST['us'];
        $realmSlug = strtolower(str_replace("'", '', str_replace(' ', '-', $realm)));
        $nameLower = strtolower($name);

        try {
            $token    = $this->getToken();
            $response = Http::withToken($token)
                ->get("{$host}/profile/wow/character/{$realmSlug}/{$nameLower}/mythic-keystone-profile/season/{$seasonId}", [
                    'namespace' => $namespace,
                    'locale'    => 'pt_BR',
                ]);

            if (!$response->successful()) return [];

            $bestRuns = $response->json('best_runs') ?? [];
            $runs = [];

            foreach ($bestRuns as $run) {
                $runs[] = [
                    'dungeon_name' => $run['dungeon']['name'] ?? 'Unknown',
                    'key_level'    => (int) ($run['keystone_level'] ?? 0),
                    'duration_ms'  => (int) ($run['duration'] ?? 0),
                    'timed'        => (bool) ($run['is_completed_within_time'] ?? false),
                    'completed_at' => isset($run['completed_timestamp'])
                        ? date('Y-m-d', (int) ($run['completed_timestamp'] / 1000))
                        : null,
                    'week'         => (int) ($run['mythic_rating']['rating'] ?? 0), // semana calculável
                ];
            }

            return $runs;

        } catch (\Throwable $e) {
            Log::warning("BlizzardService: keystone falhou para {$name}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca progressão de raid por dificuldade via encounters/raids.
     * Retorna array: [instance_name, difficulty, bosses_killed, total_bosses]
     */
    public function getRaidProgress(string $name, string $realm, string $region): array
    {
        $region    = strtolower($region);
        $namespace = 'dynamic-' . (self::REGION_NAMESPACE[$region] ?? 'us');
        $host      = self::REGION_HOST[$region] ?? self::REGION_HOST['us'];
        $realmSlug = strtolower(str_replace("'", '', str_replace(' ', '-', $realm)));
        $nameLower = strtolower($name);

        // Raids da season atual (Midnight S1)
        $currentRaids = ['Voidspire', 'Dreamrift', "March on Quel'Danas"];

        try {
            $token    = $this->getToken();
            $response = Http::withToken($token)
                ->get("{$host}/profile/wow/character/{$realmSlug}/{$nameLower}/encounters/raids", [
                    'namespace' => $namespace,
                    'locale'    => 'pt_BR',
                ]);

            if (!$response->successful()) return [];

            $expansions = $response->json('expansions') ?? [];
            $result = [];

            foreach ($expansions as $expansion) {
                $instances = $expansion['instances'] ?? [];
                foreach ($instances as $instance) {
                    $instanceName = $instance['instance']['name'] ?? '';
                    if (!in_array($instanceName, $currentRaids)) continue;

                    $modes = $instance['modes'] ?? [];
                    foreach ($modes as $mode) {
                        $diffLabel = strtolower($mode['difficulty']['type'] ?? '');
                        $diffMap   = ['NORMAL' => 'normal', 'HEROIC' => 'heroic', 'MYTHIC' => 'mythic', 'LFR' => 'lfr'];
                        $diff      = $diffMap[strtoupper($diffLabel)] ?? $diffLabel;

                        $progress     = $mode['progress'] ?? [];
                        $bossesKilled = (int) ($progress['completed_count'] ?? 0);
                        $totalBosses  = (int) ($progress['total_count'] ?? 0);

                        if ($totalBosses === 0) continue;

                        $result[] = [
                            'instance_name' => $instanceName,
                            'difficulty'    => $diff,
                            'bosses_killed' => $bossesKilled,
                            'total_bosses'  => $totalBosses,
                        ];
                    }
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::warning("BlizzardService: raid progress falhou para {$name}: " . $e->getMessage());
            return [];
        }
    }
}
