<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::create([
            'name' => 'admin',
        ]);
        Role::create([
            'name' => 'school',
        ]);
        Role::create([
            'name' => 'driver',
        ]);
        Role::create([
            'name' => 'parent',
        ]);
        Role::create([
            'name' => 'guardian',
        ]);
        Role::create([
            'name' => 'student',
        ]);
    }
}
