<?php
namespace App\Modules\Performances;

use Database\Factories\PerformanceFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Performance extends Model
{
    use HasFactory;

    protected static function newFactory(): PerformanceFactory
    {
        return PerformanceFactory::new();
    }

    protected $fillable = [
        'player_id', 'raid_id', 'boss_name',
        'dps_best', 'dps_avg', 'hps', 'parse_pct',
        'ilvl_at_time', 'spec_at_time', 'kills',
    ];

    protected $casts = [
        'dps_best'     => 'integer',
        'dps_avg'      => 'integer',
        'parse_pct'    => 'integer',
        'ilvl_at_time' => 'integer',
        'kills'        => 'integer',
    ];

    public function player()
    {
        return $this->belongsTo(\App\Modules\Players\Player::class);
    }

    public function raid()
    {
        return $this->belongsTo(\App\Modules\Raids\Raid::class);
    }
}
