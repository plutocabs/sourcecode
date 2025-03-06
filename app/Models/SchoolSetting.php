<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory;

    //guarded
    protected $guarded = ['id', 'created_at', 'updated_at'];

    //school
    public function school()
    {
        return $this->belongsTo(User::class, 'school_id');
    }
}
