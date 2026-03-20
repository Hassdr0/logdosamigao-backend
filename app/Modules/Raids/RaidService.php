<?php
namespace App\Modules\Raids;

class RaidService
{
    public function __construct(private Raid $model) {}

    public function getAll(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->model->orderBy('date', 'desc')->limit(50);
        if (!empty($filters['difficulty'])) $query->where('difficulty', $filters['difficulty']);
        if (!empty($filters['instance']))   $query->where('instance_name', 'like', '%' . $filters['instance'] . '%');
        return $query->get();
    }

    public function findById(int $id): Raid
    {
        return $this->model->with('performances.player')->findOrFail($id);
    }
}
