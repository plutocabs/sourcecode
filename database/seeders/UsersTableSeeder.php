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

class UsersTableSeeder extends Seeder
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
    }
}
