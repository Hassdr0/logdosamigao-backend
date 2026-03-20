<?php
namespace App\Modules\Performances;

use Illuminate\Support\Facades\DB;
use App\Modules\Players\Player;

class PerformanceService
{
    public function __construct(private Performance $model) {}

    public function getRankings(array $filters = []): array
    {
        $query = $this->model
            ->select(
                'players.id',
                'players.name',
                'players.realm',
                'players.class',
                'players.spec',
                'players.item_level',
                DB::raw('AVG(performances.dps_avg) as avg_dps'),
                DB::raw('AVG(performances.parse_pct) as avg_parse'),
                DB::raw('MAX(performances.parse_pct) as best_parse'),
                DB::raw('SUM(performances.kills) as total_kills'),
                DB::raw('COUNT(DISTINCT performances.raid_id) as total_raids')
            )
            ->join('players', 'players.id', '=', 'performances.player_id')
            ->join('raids',   'raids.id',   '=', 'performances.raid_id')
            ->where('players.is_active', true);

        if (!empty($filters['difficulty'])) {
            $query->where('raids.difficulty', $filters['difficulty']);
        }
        if (!empty($filters['raid'])) {
            $query->where('raids.instance_name', 'like', '%' . $filters['raid'] . '%');
        }

        return $query
            ->groupBy('players.id', 'players.name', 'players.realm', 'players.class', 'players.spec', 'players.item_level')
            ->orderByDesc('avg_dps')
            ->get()
            ->map(fn($row) => [
                'id'          => $row->id,
                'name'        => $row->name,
                'realm'       => $row->realm,
                'class'       => $row->class,
                'spec'        => $row->spec,
                'item_level'  => $row->item_level,
                'avg_dps'     => (int) round($row->avg_dps),
                'avg_parse'   => (int) round($row->avg_parse),
                'best_parse'  => (int) $row->best_parse,
                'total_kills' => (int) $row->total_kills,
                'total_raids' => (int) $row->total_raids,
            ])
            ->toArray();
    }

    public function getHighlights(array $filters = []): array
    {
        $rankings = $this->getRankings($filters);
        if (empty($rankings)) return [];

        $col     = collect($rankings);
        $topIlvl = Player::where('is_active', true)->orderByDesc('item_level')->first();

        return [
            'rei_do_dps'  => $col->sortByDesc('avg_dps')->first(),
            'maior_parse' => $col->sortByDesc('best_parse')->first(),
            'mais_raids'  => $col->sortByDesc('total_raids')->first(),
            'maior_ilvl'  => $topIlvl ? ['name' => $topIlvl->name, 'item_level' => $topIlvl->item_level] : null,
        ];
    }
}
