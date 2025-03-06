<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //next_stop_is_your_pickup_location
        EventType::create([
            'notification_name' => 'next_stop_is_your_pickup_location_notification_on_off',
            'title' => 'Next Stop Is Your Pickup Location. Please Get Ready',
        ]);

        //bus_near_pickup_location_notification_by_distance
        EventType::create([
            'notification_name' => 'bus_near_pickup_location_notification_by_distance',
            'title' => 'Bus Near Pickup Location',
        ]);

        //bus_arrived_at_pickup_location
        EventType::create([
            'notification_name' => 'bus_arrived_at_pickup_location_notification_on_off',
            'title' => 'Bus Arrived At Pickup Location',
        ]);

        //student_is_picked_up
        EventType::create([
            'notification_name' => 'student_is_picked_up_notification_on_off',
            'title' => 'Student Is Picked Up',
        ]);

        //student_is_missed_pickup
        EventType::create([
            'notification_name' => 'student_is_missed_pickup_notification_on_off',
            'title' => 'Bus Left Pickup Location. Student Is Missed Pickup',
        ]);

        //bus_arrived_at_school
        EventType::create([
            'notification_name' => 'bus_arrived_at_school_notification_on_off',
            'title' => 'Bus Arrived At School',
        ]);

        //bus_near_drop_off_location_notification_on_off
        EventType::create([
            'notification_name' => 'bus_near_drop_off_location_notification_on_off',
            'title' => 'Bus Near DropOff Location',
        ]);


        //bus_arrived_at_drop_off_location
        EventType::create([
            'notification_name' => 'bus_arrived_at_drop_off_location_notification_on_off',
            'title' => 'Bus Arrived At DropOff Location',
        ]);
    }
}
