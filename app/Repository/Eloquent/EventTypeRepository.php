<?php

namespace App\Repository\Eloquent;

use App\Models\EventType;
use App\Repository\EventTypeRepositoryInterface;


/**
 * Class EventTypeRepository.
 */
class EventTypeRepository extends BaseRepository implements EventTypeRepositoryInterface
{
    /**
     * @var EventType
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param EventType $model
     */
    public function __construct(EventType $model)
    {
        $this->model = $model;
    }
}

