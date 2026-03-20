<?php
namespace App\Modules\Raids;

use Database\Factories\RaidFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Raid extends Model
{
    use HasFactory;

    protected static function newFactory(): RaidFactory
    {
        return RaidFactory::new();
    }

    protected $fillable = [
        'wcl_report_id', 'instance_name', 'difficulty',
        'date', 'bosses_killed', 'total_bosses', 'wcl_url',
    ];

    protected $casts = ['date' => 'date'];

    public function performances()
    {
        return $this->hasMany(\App\Modules\Performances\Performance::class);
    }
}
