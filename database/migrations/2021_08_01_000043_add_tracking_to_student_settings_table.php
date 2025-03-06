<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTrackingToStudentSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('student_settings', function (Blueprint $table) {
            //pickup settings
            $table->double('pickup_lat')->nullable();
            $table->double('pickup_lng')->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('pickup_place_id')->nullable();

            //drop off settings
            $table->double('drop_off_lat')->nullable();
            $table->double('drop_off_lng')->nullable();
            $table->string('drop_off_address')->nullable();
            $table->string('drop_off_place_id')->nullable();

            //morning bus id
            $table->unsignedInteger('morning_bus_id')->nullable();
            $table->foreign('morning_bus_id')->references('id')->on('buses')->onDelete('set null');

            //afternoon bus id
            $table->unsignedInteger('afternoon_bus_id')->nullable();
            $table->foreign('afternoon_bus_id')->references('id')->on('buses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('student_settings', function (Blueprint $table) {
            $table->dropColumn('pickup_lat');
            $table->dropColumn('pickup_long');
            $table->dropColumn('pickup_address');
            $table->dropColumn('pickup_place_id');

            $table->dropColumn('drop_off_lat');
            $table->dropColumn('drop_off_long');
            $table->dropColumn('drop_off_address');
            $table->dropColumn('drop_off_place_id');

            $table->dropForeign(['morning_bus_id']);
            $table->dropColumn('morning_bus_id');

            $table->dropForeign(['afternoon_bus_id']);
            $table->dropColumn('afternoon_bus_id');
        });
    }
}
