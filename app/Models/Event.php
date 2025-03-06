<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    //guarded
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    //event type
    public function eventType()
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }
}
