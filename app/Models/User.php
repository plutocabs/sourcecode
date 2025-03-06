<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_admin' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
      return $this->is_admin;
    }

    public function messages()
    {
      return $this->hasMany(Message::class);
    }

    public function getMustVerifyEmailAttribute()
    {
        return config('auth.must_verify_email');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }
    //bus
    public function bus()
    {
        return $this->hasOne(Bus::class, 'driver_id');
    }
    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class)->orderBy('created_at', 'desc')->where('seen',0);
    }

    public function favoritePlaces()
    {
        return $this->hasMany(Place::class)->where('favorite', 1);
    }

    public function recentPlaces()
    {
        return $this->hasMany(Place::class)->where('favorite', 0);
    }

    //reservations
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'user_id');
    }

    //payments
    public function schoolCharges()
    {
        return $this->hasMany(Charge::class, 'school_id');
    }
    public function parentCharges()
    {
        return $this->hasMany(Charge::class, 'parent_id');
    }


    //bank account
    public function bankAccount()
    {
        //return the last saved bank account
        return $this->hasOne(BankAccount::class, 'user_id')->latest();
    }

    //mobile money
    public function mobileMoneyAccount()
    {
        return $this->hasOne(MobileMoneyAccount::class, 'user_id')->latest();
    }

    //paypal
    public function paypalAccount()
    {
        return $this->hasOne(PaypalAccount::class, 'user_id')->latest();
    }

    //driver information
    public function driverInformation()
    {
        return $this->hasOne(DriverInformation::class, 'driver_id');
    }
    // /////////////////////student///////////////////////////////////
    //student guardians
    public function studentGuardians()
    {
        return $this->hasMany(StudentGuardian::class, 'student_id');
    }
    //student parent
    public function studentParents()
    {
        $parent_ids = [];
        $guardians = StudentGuardian::where('student_id', $this->id)->with('guardian')->get();
        //get all guardians for student with role_id = 4
        foreach ($guardians as $guardian) {
            if ($guardian->guardian->role_id == 4) {
                $parent_ids[] = $guardian->guardian->id;
            }
        }
        return $this->hasMany(User::class, 'id')->whereIn('id', $parent_ids);
    }
    // /////////////////////Guardian/////////////////////////////////
    //guardian students
    public function guardianStudents()
    {
        return $this->hasMany(StudentGuardian::class, 'guardian_id');
    }
    // /////////////////////school///////////////////////////////////
    //school students
    public function schoolStudents()
    {
        //users with role_id = 6
        return $this->hasMany(User::class, 'school_id')->where('role_id', 6);
    }
    //routes
    public function schoolRoutes()
    {
        return $this->hasMany(Route::class, 'school_id');
    }
    //stops
    public function schoolStops()
    {
        return $this->hasMany(Stop::class, 'school_id');
    }
    //trips
    public function schoolTrips()
    {
        // get trips for this school based on routes
        return $this->hasManyThrough(Trip::class, Route::class, 'school_id', 'route_id');
    }

    //drivers
    public function schoolDrivers()
    {
        return $this->hasMany(User::class, 'school_id')->where('role_id', 3);
    }

    //buses
    public function schoolBuses()
    {
        return $this->hasMany(Bus::class, 'school_id');
    }

    //student school
    public function studentSchool()
    {
        return $this->belongsTo(User::class, 'school_id');
    }

    //driver school
    public function driverSchool()
    {
        return $this->belongsTo(User::class, 'school_id');
    }

    //studentSettings
    public function studentSettings()
    {
        return $this->hasOne(StudentSetting::class, 'student_id');
    }

    //schoolSettings
    public function schoolSettings()
    {
        return $this->hasOne(SchoolSetting::class, 'school_id');
    }
}
