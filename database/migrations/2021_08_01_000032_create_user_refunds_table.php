<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_refunds', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('user_id'); //school or parent
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->double('amount');
            $table->date('refund_date');

            //reason
            $table->text('reason')->nullable()->default(null);

            //student_trip_id
            $table->unsignedInteger('student_trip_id');
            $table->foreign('student_trip_id')->references('id')->on('student_trips')->onDelete('cascade');

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
        Schema::dropIfExists('user_refunds');
    }
}
