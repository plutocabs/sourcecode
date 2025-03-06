<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_settings', function (Blueprint $table) {
            $table->increments('id');

            //student id
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');

            // pickup stop id
            $table->unsignedInteger('pickup_route_stop_id')->nullable();
            $table->foreign('pickup_route_stop_id')->references('id')->on('route_stops')->onDelete('set null');

            // drop off stop id
            $table->unsignedInteger('drop_off_route_stop_id')->nullable();
            $table->foreign('drop_off_route_stop_id')->references('id')->on('route_stops')->onDelete('set null');

            //pickup_trip_id
            $table->unsignedInteger('pickup_trip_id')->nullable();
            $table->foreign('pickup_trip_id')->references('id')->on('trips')->onDelete('set null');

            //drop_off_trip_id
            $table->unsignedInteger('drop_off_trip_id')->nullable();
            $table->foreign('drop_off_trip_id')->references('id')->on('trips')->onDelete('set null');

            //absent on
            $table->date('absent_on')->nullable();

            //1 - next stop is your pickup location notification on off
            $table->boolean('next_stop_is_your_pickup_location_notification_on_off')->default(0);

            //2- bus near pickup location notification by distance
            $table->integer('bus_near_pickup_location_notification_by_distance')->nullable();

            //3 - bus arrived at pickup location notification on off
            $table->boolean('bus_arrived_at_pickup_location_notification_on_off')->default(0);

            //4 - student is picked up notification on off
            $table->boolean('student_is_picked_up_notification_on_off')->default(0);

            //5- student is missed pickup notification on off
            $table->boolean('student_is_missed_pickup_notification_on_off')->default(0);

            //6 - bus arrived at school notification on off
            $table->boolean('bus_arrived_at_school_notification_on_off')->default(0);


            //7- bus near drop off location notification on off
            $table->boolean('bus_near_drop_off_location_notification_on_off')->default(0);

            //8- bus arrived at drop off location notification on off
            $table->boolean('bus_arrived_at_drop_off_location_notification_on_off')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_settings');
    }
}
