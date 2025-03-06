<?php

namespace App\Repository\Eloquent;

use App\Models\Plan;
use App\Repository\PlanRepositoryInterface;


/**
 * Class PlanRepository.
 */
class PlanRepository extends BaseRepository implements PlanRepositoryInterface
{
    /**
     * @var Plan
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Plan $model
     */
    public function __construct(Plan $model)
    {
        $this->model = $model;
    }
}

