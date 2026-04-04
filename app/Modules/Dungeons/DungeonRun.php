<?php
namespace App\Modules\Dungeons;

use Illuminate\Database\Eloquent\Model;

class DungeonRun extends Model
{
    protected $fillable = [
        'player_id', 'dungeon_name', 'key_level',
        'score', 'rank_percent', 'median_percent', 'best_dps', 'total_runs', 'spec',
        'server_rank', 'region_rank', 'world_rank', 'best_time_ms',
    ];

    public function player()
    {
        return $this->belongsTo(\App\Modules\Players\Player::class);
    }
}
