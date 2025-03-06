<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentTrip extends Model
{
    use HasFactory;

    //guarded
    protected $guarded = ['id', 'created_at', 'updated_at'];

    //planned trip
    public function plannedTrip()
    {
        return $this->belongsTo(PlannedTrip::class, 'planned_trip_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
