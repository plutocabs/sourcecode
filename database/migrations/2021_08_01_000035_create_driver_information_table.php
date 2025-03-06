<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverInformationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('driver_information', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->string('address');
            $table->string('email');
            $table->string('license_number');

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
        Schema::dropIfExists('driver_information');
    }
}
