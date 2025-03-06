<?php

namespace App\Repository\Eloquent;

use App\Models\Consumption;
use App\Repository\ConsumptionRepositoryInterface;


/**
 * Class ConsumptionRepository.
 */
class ConsumptionRepository extends BaseRepository implements ConsumptionRepositoryInterface
{
    /**
     * @var Consumption
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Consumption $model
     */
    public function __construct(Consumption $model)
    {
        $this->model = $model;
    }
}

