<?php
namespace App\Modules\Performances;

use Illuminate\Support\Facades\DB;
use App\Modules\Players\Player;

class PerformanceService
{
    public function __construct(private Performance $model) {}

    public function getRankings(array $filters = []): array
    {
        $difficultyFilter = $filters['difficulty'] ?? null;
        $raidFilter       = $filters['raid'] ?? null;

        $base = $this->model
            ->join('players', 'players.id', '=', 'performances.player_id')
            ->join('raids',   'raids.id',   '=', 'performances.raid_id')
            ->where('players.is_active', true);

        if ($raidFilter) {
            $base->where('raids.instance_name', 'like', '%' . $raidFilter . '%');
        }

        // Agregar métricas gerais (dps, kills, raids)
        $aggQuery = (clone $base)->select(
            'players.id',
            'players.name',
            'players.realm',
            'players.class',
            'players.spec',
            'players.item_level',
            DB::raw('AVG(performances.dps_avg) as avg_dps'),
            DB::raw('SUM(performances.kills) as total_kills'),
            DB::raw('COUNT(DISTINCT performances.raid_id) as total_raids')
        );
        if ($difficultyFilter) {
            $aggQuery->where('raids.difficulty', $difficultyFilter);
        }
        $agg = $aggQuery
            ->groupBy('players.id','players.name','players.realm','players.class','players.spec','players.item_level')
            ->orderByDesc('avg_dps')
            ->get()
            ->keyBy('id');

        if ($agg->isEmpty()) return [];

        // Parse médio por dificuldade (separado para não misturar)
        $parseQuery = (clone $base)->select(
            'players.id',
            'raids.difficulty',
            DB::raw('AVG(performances.parse_pct) as avg_parse')
        );
        if ($difficultyFilter) {
            $parseQuery->where('raids.difficulty', $difficultyFilter);
        }
        $parseRows = $parseQuery
            ->groupBy('players.id', 'raids.difficulty')
            ->get();

        // Indexar: [player_id][difficulty] = avg_parse
        $parseMap = [];
        foreach ($parseRows as $row) {
            $parseMap[$row->id][$row->difficulty] = (int) round($row->avg_parse);
        }

        $difficultyOrder = ['mythic' => 4, 'heroic' => 3, 'normal' => 2, 'lfr' => 1];

        return $agg->map(function ($row) use ($parseMap, $difficultyFilter, $difficultyOrder) {
            $byDiff = $parseMap[$row->id] ?? [];

            // Parse para a coluna principal: dificuldade filtrada, ou a mais alta disponível
            if ($difficultyFilter) {
                $mainParse = $byDiff[$difficultyFilter] ?? 0;
            } else {
                $best = collect($byDiff)->keys()
                    ->sortByDesc(fn($d) => $difficultyOrder[$d] ?? 0)
                    ->first();
                $mainParse = $best ? $byDiff[$best] : 0;
            }

            return [
                'id'                 => $row->id,
                'name'               => $row->name,
                'realm'              => $row->realm,
                'class'              => $row->class,
                'spec'               => $row->spec,
                'item_level'         => $row->item_level,
                'avg_dps'            => (int) round($row->avg_dps),
                'avg_parse'          => $mainParse,
                'parse_by_difficulty'=> $byDiff,
                'total_kills'        => (int) $row->total_kills,
                'total_raids'        => (int) $row->total_raids,
            ];
        })->values()->toArray();
    }

    public function getHighlights(array $filters = []): array
    {
        $rankings = $this->getRankings($filters);
        if (empty($rankings)) return [];

        $col     = collect($rankings);
        $topIlvl = Player::where('is_active', true)->orderByDesc('item_level')->first();

        return [
            'mais_raids'  => $col->sortByDesc('total_raids')->first(),
            'mais_kills'  => $col->sortByDesc('total_kills')->first(),
            'maior_ilvl'  => $topIlvl ? ['name' => $topIlvl->name, 'item_level' => $topIlvl->item_level] : null,
        ];
    }
}
