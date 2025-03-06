<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->increments('id');

            //user
            $table->unsignedInteger('user_id'); //school or parent or driver
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            //event_type
            $table->unsignedInteger('event_type_id');
            $table->foreign('event_type_id')->references('id')->on('event_types');

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
        Schema::dropIfExists('events');
    }
}
