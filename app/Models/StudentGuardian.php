<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;

class StudentGuardian extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }
}
