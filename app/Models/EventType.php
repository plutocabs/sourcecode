<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventType extends Model
{
    use HasFactory;

    //guarded
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
}
