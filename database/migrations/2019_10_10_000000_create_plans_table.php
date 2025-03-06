<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');

            //coin count
            $table->unsignedInteger('coin_count')->default(0);
            //price
            $table->unsignedInteger('price')->default(0);
            //schools plan or parents plan
            $table->unsignedInteger('plan_type')->default(0); //0 for schools, 1 for parents
            //plan name
            $table->string('name')->nullable()->default(null);
            //single buy or multiple buy
            $table->unsignedInteger('availability'); //1 for single buy, 2 for multiple buy

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
        Schema::dropIfExists('plans');
    }
}
