<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    //guarded
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    //plan
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    //school
    public function school()
    {
        return $this->belongsTo(User::class, 'school_id');
    }

    //parent
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
