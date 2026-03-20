<?php

namespace App\Modules\Base\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\RelationNotFoundException;

class ApiService
{
    protected $invalid_constraints = [
        'skip', 'take', 'orderBy', 'token',
    ];

    protected $reserved_keywords = [
        'has', 'skip', 'take', 'orderBy', 'token',
    ];

    public function __construct(Model $model, array $custom_filters = [], array $custom_sorts = [])
    {
        $this->model          = $model;
        $this->custom_filters = $custom_filters;
        $this->custom_sorts   = $custom_sorts;
    }

    public function get(array $data = [])
    {
        $wheres    = $data['wheres'] ?? [];
        $relations = $data['relations'] ?? [];
        $select    = $data['select'] ?? [];

        if (isset($data['paginate']) && $data['paginate'] === 'true') {
            $take = isset($data['take']) ? $data['take'] : 15;
            return $this->buildQuery($wheres, $relations, $select)->paginate($take);
        }

        return $this->buildQuery($wheres, $relations, $select)->get();
    }

    public function find(array $data = [])
    {
        $wheres    = $data['wheres'] ?? [];
        $relations = $data['relations'] ?? [];
        $select    = $data['select'] ?? [];

        return $this->buildQuery($wheres, $relations, $select)->firstOrFail();
    }

    protected function buildQuery(array $wheres = [], array $relations = [], array $select = [])
    {
        $query = $this->model;
        $query = $this->with($query, $relations);
        $this->insertQueryConstraints($query, $wheres);
        $this->insertQueryResultParameters($query, $wheres);

        if (!empty($select)) {
            $query = $query->select($select);
        }

        return $query;
    }

    protected function insertQueryConstraints(&$query, $wheres)
    {
        foreach ($wheres as $key => $value) {
            if ($value == 'null') $value = null;

            if (in_array($key, $this->invalid_constraints)) {
                continue;
            } elseif ($key === 'has') {
                $query = $query->whereHas($value);
            } elseif (is_array($value)) {
                $query = $query->whereIn($key, $value);
            } else {
                if (is_string($value) && $value[0] === '%' && $value[strlen($value) - 1] === '%') {
                    $query = $query->where($key, 'like', $value);
                } else {
                    $query = $query->where($key, $value);
                }
            }
        }
    }

    protected function insertQueryResultParameters(&$query, $wheres)
    {
        foreach ($wheres as $key => $value) {
            if ($key === 'skip') {
                $query = $query->skip($value);
            } elseif ($key === 'take') {
                $query = $query->take($value);
            } elseif ($key === 'orderBy') {
                $parsed = $this->parseOrderBy($value);
                $query  = $query->orderBy($parsed['value'], $parsed['direction']);
            }
        }
        return $query;
    }

    protected function parseOrderBy(string $value)
    {
        if (substr($value, 0, 1) === '!') {
            return ['value' => substr($value, 1), 'direction' => 'desc'];
        }
        return ['value' => $value, 'direction' => 'asc'];
    }

    public function with($model, array $relations = [])
    {
        if (!empty($relations)) {
            try {
                $model = call_user_func_array([$model, 'with'], $relations);
            } catch (RelationNotFoundException $e) {
                \Log::warning($e->getMessage());
            }
        }
        return $model;
    }
}
