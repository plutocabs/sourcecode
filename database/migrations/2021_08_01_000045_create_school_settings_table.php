<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchoolSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->increments('id');

            //school id
            $table->unsignedInteger('school_id');
            $table->foreign('school_id')->references('id')->on('users')->onDelete('cascade');

            //place_id
            $table->string('place_id')->nullable();

            //address
            $table->string('address')->nullable();

            //lat
            $table->string('lat')->nullable();

            //lng
            $table->string('lng')->nullable();

            //off day of the week
            $table->boolean('saturday')->default(false);
            $table->boolean('sunday')->default(false);
            $table->boolean('monday')->default(false);
            $table->boolean('tuesday')->default(false);
            $table->boolean('wednesday')->default(false);
            $table->boolean('thursday')->default(false);
            $table->boolean('friday')->default(false);

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
        Schema::dropIfExists('school_settings');
    }
}
