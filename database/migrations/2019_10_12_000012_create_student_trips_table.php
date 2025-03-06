<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentTripsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_trips', function (Blueprint $table) {
            $table->increments('id');

            //student id
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('planned_trip_id');
            $table->foreign('planned_trip_id')->references('id')->on('planned_trips')->onDelete('cascade');

            $table->date('riding_date');

            $table->unsignedInteger('start_stop_id')->nullable();
            $table->foreign('start_stop_id')->references('id')->on('stops')->onDelete('set null');

            $table->unsignedInteger('end_stop_id')->nullable();
            $table->foreign('end_stop_id')->references('id')->on('stops')->onDelete('set null');

            //planned_start_time
            $table->time('planned_start_time');

            //ride status
            $table->unsignedInteger('ride_status')->default(0);
            //0 not ride, 1-ride, 2-miss ride, 3-drop off, 4 - cancelled by admin

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
        Schema::dropIfExists('student_trips');
    }
}
