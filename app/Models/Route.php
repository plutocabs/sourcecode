<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function stops()
    {
        return $this->belongsToMany(Stop::class, 'route_stops');
    }
    public function routeStops()
    {
        return $this->hasMany(RouteStop::class);
    }

    //trips
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
