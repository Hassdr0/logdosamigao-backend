<?php
namespace App\Modules\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarcraftLogsService
{
    private const TOKEN_CACHE_KEY = 'wcl_oauth_token';

    public function getToken(): string
    {
        return Cache::remember(
            self::TOKEN_CACHE_KEY,
            config('wcl.token_ttl', 82800),
            fn() => $this->fetchToken()
        );
    }

    private function fetchToken(): string
    {
        $response = Http::asForm()->post(config('wcl.token_url'), [
            'grant_type'    => 'client_credentials',
            'client_id'     => config('wcl.client_id'),
            'client_secret' => config('wcl.client_secret'),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'WCL OAuth token request failed: ' . $response->status()
            );
        }

        return $response->json('access_token');
    }

    public function getCharacterReports(
        string $name,
        string $realm,
        string $region,
        float $startTime = 0
    ): array {
        $query = <<<'GQL'
        query GetCharacterReports($name: String!, $realm: String!, $region: String!) {
          characterData {
            character(name: $name, serverSlug: $realm, serverRegion: $region) {
              gearScore
              recentReports(limit: 100) {
                data {
                  code
                  startTime
                  title
                  zone { name }
                }
              }
            }
          }
        }
        GQL;

        $response = $this->graphql($query, [
            'name'   => $name,
            'realm'  => $realm,
            'region' => $region,
        ]);

        $char = $response['data']['characterData']['character'] ?? [];
        return [
            'reports'    => $char['recentReports']['data'] ?? [],
            'gear_score' => (int) ($char['gearScore'] ?? 0),
        ];
    }

    public function getReportRankings(string $code): array
    {
        $query = <<<'GQL'
        query GetReportRankings($code: String!) {
          reportData {
            report(code: $code) {
              startTime
              zone { name }
              rankings(playerMetric: dps)
              fights(killType: Kills) {
                id
                name
                difficulty
                kill
              }
            }
          }
        }
        GQL;

        $response = $this->graphql($query, ['code' => $code]);

        return $response['data']['reportData']['report'] ?? [];
    }

    public function getMythicPlusRankings(string $name, string $realm, string $region): array
    {
        $season   = config('wcl.current_season', 'midnight_s1');
        $zoneId   = config("wcl.seasons.{$season}.mplus_zone_id", 47);

        $query = <<<GQL
        query GetMythicPlus(\$name: String!, \$realm: String!, \$region: String!) {
          characterData {
            character(name: \$name, serverSlug: \$realm, serverRegion: \$region) {
              zoneRankings(zoneID: {$zoneId})
            }
          }
        }
        GQL;

        $response = $this->graphql($query, [
            'name'   => $name,
            'realm'  => $realm,
            'region' => $region,
        ]);

        return $response['data']['characterData']['character']['zoneRankings'] ?? [];
    }

    private function graphql(string $query, array $variables = []): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->post(config('wcl.api_url'), [
                'query'     => $query,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'WCL GraphQL request failed: ' . $response->status()
            );
        }

        $body = $response->json();

        if (!empty($body['errors'])) {
            $msg = collect($body['errors'])->pluck('message')->implode('; ');
            throw new \RuntimeException('WCL GraphQL errors: ' . $msg);
        }

        return $body;
    }
}
