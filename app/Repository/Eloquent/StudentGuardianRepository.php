<?php

namespace App\Repository\Eloquent;

use App\Models\StudentGuardian;
use App\Repository\StudentGuardianRepositoryInterface;


/**
 * Class StudentGuardianRepository.
 */
class StudentGuardianRepository extends BaseRepository implements StudentGuardianRepositoryInterface
{
    /**
     * @var StudentGuardian
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param StudentGuardian $model
     */
    public function __construct(StudentGuardian $model)
    {
        $this->model = $model;
    }
}

