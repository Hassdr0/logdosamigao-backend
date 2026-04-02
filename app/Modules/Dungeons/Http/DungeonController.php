<?php
namespace App\Modules\Dungeons\Http;

use Illuminate\Http\Request;
use App\Modules\Dungeons\DungeonRun;
use App\Modules\Players\Player;

class DungeonController
{
    // GET /api/dungeons — ranking geral de M+
    public function index(Request $request)
    {
        $query = DungeonRun::with('player')
            ->join('players', 'players.id', '=', 'dungeon_runs.player_id')
            ->where('players.is_active', true)
            ->select('dungeon_runs.*');

        if ($request->dungeon) {
            $query->where('dungeon_name', $request->dungeon);
        }

        // Agregar por player: média de score, melhor key, total de runs
        $runs = $query->get()->groupBy('player_id')->map(function ($playerRuns) use ($request) {
            $player  = $playerRuns->first()->player;
            $filtered = $playerRuns;

            return [
                'id'          => $player->id,
                'name'        => $player->name,
                'realm'       => $player->realm,
                'class'       => $player->class,
                'spec'        => $player->spec,
                'item_level'  => $player->item_level,
                'avg_score'   => round($filtered->avg('score'), 1),
                'best_score'  => round($filtered->max('score'), 1),
                'avg_parse'   => round($filtered->avg('rank_percent'), 1),
                'best_key'    => $filtered->max('key_level'),
                'total_runs'  => $filtered->sum('total_runs'),
                'dungeons'    => $filtered->count(),
            ];
        })->sortByDesc('avg_score')->values();

        $highlights = $this->buildHighlights($runs);

        return response()->json(['rankings' => $runs, 'highlights' => $highlights]);
    }

    // GET /api/dungeons/names — lista de nomes de dungeons
    public function names()
    {
        $names = DungeonRun::distinct()->orderBy('dungeon_name')->pluck('dungeon_name');
        return response()->json($names);
    }

    private function buildHighlights($runs)
    {
        if ($runs->isEmpty()) return (object)[];

        // Buscar o run individual com melhor rank servidor (menor número = melhor)
        $makeCard = function ($run) {
            if (!$run) return null;
            $p = $run->player;
            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'realm'        => $p->realm,
                'class'        => $p->class,
                'total_runs'   => $run->total_runs,
                'dungeon_name' => $run->dungeon_name,
                'server_rank'  => $run->server_rank,
                'region_rank'  => $run->region_rank,
                'world_rank'   => $run->world_rank,
                'best_time_ms' => $run->best_time_ms,
                'score'        => $run->score,
                'key_level'    => $run->key_level,
            ];
        };

        $activeIds = DungeonRun::join('players', 'players.id', '=', 'dungeon_runs.player_id')
            ->where('players.is_active', true)
            ->select('dungeon_runs.*');

        $maisRunsUma  = (clone $activeIds)->orderByDesc('total_runs')->first();
        $melhorServidor = (clone $activeIds)->where('server_rank', '>', 0)->orderBy('server_rank')->first();
        $melhorMundo    = (clone $activeIds)->where('world_rank', '>', 0)->orderBy('world_rank')->first();
        $melhorRegiao   = (clone $activeIds)->where('region_rank', '>', 0)->orderBy('region_rank')->first();

        // Eager load players
        collect([$maisRunsUma, $melhorServidor, $melhorMundo, $melhorRegiao])
            ->filter()->each(fn($r) => $r->load('player'));

        return [
            'melhor_score'     => $runs->sortByDesc('best_score')->first(),
            'mais_runs_uma'    => $makeCard($maisRunsUma),
            'melhor_servidor'  => $makeCard($melhorServidor),
            'melhor_mundo'     => $makeCard($melhorMundo),
        ];
    }
}
