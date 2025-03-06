<?php

namespace App\Repository\Eloquent;

use App\Models\Event;
use App\Repository\EventRepositoryInterface;


/**
 * Class EventRepository.
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    /**
     * @var Event
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Event $model
     */
    public function __construct(Event $model)
    {
        $this->model = $model;
    }
}

