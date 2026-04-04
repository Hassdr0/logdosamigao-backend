<?php
namespace App\Modules\Players;

use Illuminate\Database\Eloquent\Model;

class PlayerKeystone extends Model
{
    protected $fillable = [
        'player_id', 'season_id', 'week', 'dungeon_name',
        'key_level', 'duration_ms', 'timed', 'completed_at',
    ];

    protected $casts = [
        'timed'        => 'boolean',
        'completed_at' => 'date',
        'key_level'    => 'integer',
        'duration_ms'  => 'integer',
        'week'         => 'integer',
    ];
}
