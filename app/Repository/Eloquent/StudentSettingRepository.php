<?php

namespace App\Repository\Eloquent;

use App\Models\StudentSetting;
use App\Repository\StudentSettingRepositoryInterface;


/**
 * Class StudentSettingRepository.
 */
class StudentSettingRepository extends BaseRepository implements StudentSettingRepositoryInterface
{
    /**
     * @var StudentSetting
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param StudentSetting $model
     */
    public function __construct(StudentSetting $model)
    {
        $this->model = $model;
    }
}

