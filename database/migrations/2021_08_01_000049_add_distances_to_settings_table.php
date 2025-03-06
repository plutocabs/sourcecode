<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDistancesToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            //add distance_to_drop_off and distance_to_pick_up with default value of 100
            $table->integer('distance_to_drop_off')->default(100);
            $table->integer('distance_to_pick_up')->default(100);
            //add distance_to_slow_down with default value of 1000
            $table->integer('distance_to_slow_down')->default(1000);
            //add max search radius with default value of 1000
            $table->integer('max_search_radius')->default(1000);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('distance_to_drop_off');
            $table->dropColumn('distance_to_pick_up');
            $table->dropColumn('distance_to_slow_down');
            $table->dropColumn('max_search_radius');
        });
    }
}
