<?php
namespace App\Modules\Players;

class PlayerService
{
    public function __construct(private Player $model) {}

    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->where('is_active', true)->orderBy('item_level', 'desc')->get();
    }

    public function findByRealmAndName(string $realm, string $name): Player
    {
        return $this->model
            ->where('realm', strtolower($realm))
            ->where('name', $name)
            ->firstOrFail();
    }
}
