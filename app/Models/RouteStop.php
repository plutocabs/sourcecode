<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function routeStopDirections()
    {
        return $this->hasMany(RouteStopDirection::class, 'route_stop_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }


    //stop
    public function stop()
    {
        return $this->belongsTo(Stop::class, 'stop_id');
    }

    //pickup Students
    public function pickupStudents()
    {
        return $this->hasMany(StudentSetting::class, 'pickup_route_stop_id');
    }

    //drop off Students
    public function dropOffStudents()
    {
        return $this->hasMany(StudentSetting::class, 'drop_off_route_stop_id');
    }
}
