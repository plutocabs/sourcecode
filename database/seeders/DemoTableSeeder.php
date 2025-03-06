<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan;
use App\Models\Charge;
use App\Models\Bus;
use App\Models\StudentGuardian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Traits\UserUtils;

class DemoTableSeeder extends Seeder
{
    use UserUtils;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create super admin account
        $adminUser = User::create([
            'name' => 'SuperAdmin',
            'email' => 'admin@school-trip-track.com',
            'password' => Hash::make('admin123'),
            'role_id' => 1,
            'status_id' => 1,
            'uid' => "nKDeTeVzMHRrpozr2rA9OLedGvE3"
        ]);
        $this->storeAvatar($adminUser);

        //create a school account
        $schoolUser = User::create([
            'name' => 'SchoolAdmin',
            'email' => 'school@school-trip-track.com',
            'password' => Hash::make('school123'),
            'role_id' => 2,
            'status_id' => 1,
            'balance' => 0,
            'uid' => "wy9Kw7Cm4NNbODpEe4nbjp06Ym52"
        ]);
        $this->storeAvatar($schoolUser);

        //create a parent account
        $parentUser = User::create([
            'name' => 'Parent',
            'email' => 'parent@gmail.com',
            'password' => Hash::make('parent123'),
            'role_id' => 4,
            'status_id' => 1,
            'uid' => "29aYSOcrJsYLnGcYIj7rVRZ7PMn1"
        ]);
        $this->storeAvatar($parentUser);

        //create a driver account
        $driverUser = User::create([
            'name' => 'Driver',
            'email' => 'driver@gmail.com',
            'password' => Hash::make('driver123'),
            'role_id' => 3,
            'status_id' => 1,
            'school_id' => 2,
            'uid' => "SKqEx9C8NbVrXwYi7ySHCdnmtKi2"
        ]);
        $this->storeAvatar($driverUser);

        //create a guardian account
        $guardianUser = User::create([
            'name' => 'Guardian',
            'email' => 'guardian@gmail.com',
            'password' => Hash::make('guardian123'),
            'role_id' => 5,
            'status_id' => 1,
            'uid' => "JDf3VUf3NmWrNyld5NimKRYc5QG3"
        ]);
        $this->storeAvatar($guardianUser);

        $student = User::create([
            'name' => 'Student',
            'email' => 'student@gmail.com',
            'role_id' => 6,
            'status_id' => 1,
            'school_id' => 2,
            'student_identification' => '123456789',
            'notes' => 'Student is in grade 1, class A',
        ]);
        $this->storeAvatar($student);

        StudentGuardian::create([
            'student_id' => 6,
            'guardian_id' => 3,
        ]);
        StudentGuardian::create([
            'student_id' => 6,
            'guardian_id' => 5,
        ]);


        Bus::create([
            'license' => 'B 1234',
            'capacity' => 10,
            'driver_id' => 4,
            'school_id' => 2,
        ]);

        Plan::create([
            'name' => 'Trial',
            'plan_type' => 0,
            'coin_count' => 20,
            'price' => 0,
            'availability' => 1,
        ]);

        Plan::create([
            'name' => 'Basic',
            'plan_type' => 0,
            'coin_count' => 100,
            'price' => 10,
            'availability' => 2,
        ]);

        Plan::create([
            'name' => 'Premium',
            'plan_type' => 0,
            'coin_count' => 500,
            'price' => 40,
            'availability' => 2,
        ]);

        Plan::create([
            'name' => 'Trial',
            'plan_type' => 1,
            'coin_count' => 7,
            'price' => 0,
            'availability' => 1,
        ]);

        Plan::create([
            'name' => 'Basic',
            'plan_type' => 1,
            'coin_count' => 30,
            'price' => 10,
            'availability' => 2,
        ]);

        Plan::create([
            'name' => 'Premium',
            'plan_type' => 1,
            'coin_count' => 100,
            'price' => 40,
            'availability' => 2,
        ]);

        Charge::create([
            'school_id' => 2,
            'price' => 10,
            'plan_id' => 2,
            'coin_count' => 100,
            'plan_name' => 'Basic',
            'payment_date' => '2021-08-01',
        ]);

        Charge::create([
            'school_id' => 2,
            'price' => 40,
            'plan_id' => 3,
            'coin_count' => 500,
            'plan_name' => 'Premium',
            'payment_date' => '2022-08-01',
        ]);

        //parent charge
        Charge::create([
            'parent_id' => 3,
            'price' => 10,
            'plan_id' => 5,
            'coin_count' => 30,
            'plan_name' => 'Basic',
            'payment_date' => '2023-08-01',
        ]);
    }
}
