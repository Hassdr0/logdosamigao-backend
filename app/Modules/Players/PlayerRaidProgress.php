<?php
namespace App\Modules\Players;

use Illuminate\Database\Eloquent\Model;

class PlayerRaidProgress extends Model
{
    protected $fillable = [
        'player_id', 'instance_name', 'difficulty',
        'bosses_killed', 'total_bosses',
    ];

    protected $casts = [
        'bosses_killed' => 'integer',
        'total_bosses'  => 'integer',
    ];
}
