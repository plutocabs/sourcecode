<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentGuardiansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('student_id'); //student
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('guardian_id'); //parent or guardian
            $table->foreign('guardian_id')->references('id')->on('users')->onDelete('cascade');

            $table->rememberToken();
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
        Schema::dropIfExists('student_guardians');
    }
}
