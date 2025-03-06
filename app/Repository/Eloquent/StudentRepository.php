<?php

namespace App\Repository\Eloquent;

use App\Models\Student;
use App\Repository\StudentRepositoryInterface;


/**
 * Class StudentRepository.
 */
class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    /**
     * @var Student
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Student $model
     */
    public function __construct(Student $model)
    {
        $this->model = $model;
    }
}

