<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function pickupRouteStop()
    {
        return $this->belongsTo(RouteStop::class, 'pickup_route_stop_id');
    }

    public function dropOffRouteStop()
    {
        return $this->belongsTo(RouteStop::class, 'drop_off_route_stop_id');
    }

    public function pickupTrip()
    {
        return $this->belongsTo(Trip::class, 'pickup_trip_id');
    }

    public function dropOffTrip()
    {
        return $this->belongsTo(Trip::class, 'drop_off_trip_id');
    }

    //morning bus
    public function morningBus()
    {
        return $this->belongsTo(Bus::class, 'morning_bus_id');
    }

    //afternoon bus
    public function afternoonBus()
    {
        return $this->belongsTo(Bus::class, 'afternoon_bus_id');
    }
}
