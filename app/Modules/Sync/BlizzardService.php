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

            if (!$itemLevel && !$avatarUrl) {
                return null;
            }

            return array_filter([
                'item_level' => $itemLevel,
                'avatar_url' => $avatarUrl,
            ]);

        } catch (\Throwable $e) {
            Log::warning("BlizzardService: falha ao buscar {$name}-{$realm}: " . $e->getMessage());
            return null;
        }
    }
}
