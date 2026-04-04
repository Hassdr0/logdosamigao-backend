<?php
namespace App\Modules\Players;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Model
{
    use HasFactory;

    protected static function newFactory(): PlayerFactory
    {
        return PlayerFactory::new();
    }

    protected $fillable = [
        'name', 'realm', 'region', 'class', 'spec',
        'item_level', 'wcl_character_id', 'avatar_url',
        'is_active', 'last_synced_at',
        'rio_score', 'rio_score_color', 'rio_scores_by_spec',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'last_synced_at'     => 'datetime',
        'item_level'         => 'integer',
        'rio_score'          => 'float',
        'rio_scores_by_spec' => 'array',
    ];

    public function performances()
    {
        return $this->hasMany(\App\Modules\Performances\Performance::class);
    }

    public function dungeonRuns()
    {
        return $this->hasMany(\App\Modules\Dungeons\DungeonRun::class);
    }

    public function keystones()
    {
        return $this->hasMany(PlayerKeystone::class);
    }

    public function raidProgress()
    {
        return $this->hasMany(PlayerRaidProgress::class);
    }
}
