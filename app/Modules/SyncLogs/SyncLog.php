<?php
namespace App\Modules\SyncLogs;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'player_id', 'status', 'reports_fetched',
        'error_message', 'synced_at',
    ];

    protected $casts = ['synced_at' => 'datetime'];

    public function player()
    {
        return $this->belongsTo(\App\Modules\Players\Player::class);
    }
}
