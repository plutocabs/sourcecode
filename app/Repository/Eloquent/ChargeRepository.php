<?php

namespace App\Repository\Eloquent;

use App\Models\Charge;
use App\Repository\ChargeRepositoryInterface;


/**
 * Class ChargeRepository.
 */
class ChargeRepository extends BaseRepository implements ChargeRepositoryInterface
{
    /**
     * @var Charge
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Charge $model
     */
    public function __construct(Charge $model)
    {
        $this->model = $model;
    }
}

