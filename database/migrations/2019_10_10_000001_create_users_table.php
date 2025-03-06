<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            $table->string('uid')->nullable();
            $table->string('fcm_token')->nullable()->default(null);

            $table->string('avatar')->default('avatar.png');

            $table->string('tel_number')->nullable();
            //address
            $table->string('address')->nullable();

            $table->unsignedInteger('balance')->default(0);

            $table->unsignedInteger('status_id')->default(1); //1, active, 2 pending, 3 suspended
            $table->foreign('status_id')->references('id')->on('statuses')->onDelete('cascade');

            $table->unsignedInteger('role_id'); //1, admin, 2 school, 3 driver, 4 parent, 5 guardian, 6 student
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            //plan
            $table->unsignedInteger('plan_id')->nullable();
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');

            //school_id
            $table->unsignedInteger('school_id')->nullable();
            $table->foreign('school_id')->references('id')->on('users')->onDelete('set null');

            //parent_id
            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('student_identification')->nullable();

            $table->text('notes')->nullable();
            $table->text('registration_response')->nullable();


            //request_delete_at
            $table->timestamp('request_delete_at')->nullable();

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
        Schema::dropIfExists('users');
    }
}
