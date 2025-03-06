<?php

namespace App\Repository\Eloquent;

use App\Models\SchoolSetting;
use App\Repository\SchoolSettingRepositoryInterface;


/**
 * Class SchoolSettingRepository.
 */
class SchoolSettingRepository extends BaseRepository implements SchoolSettingRepositoryInterface
{
    /**
     * @var SchoolSetting
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param SchoolSetting $model
     */
    public function __construct(SchoolSetting $model)
    {
        $this->model = $model;
    }
}

