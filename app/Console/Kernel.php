<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Traits\TripUtils;
use App\Traits\UserUtils;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    use TripUtils;
    use UserUtils;
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        //schedule the function daily
        $schedule->call(function () {
            $ret = $this->scheduleDriverTrips();
            Log::info('scheduleDriverTrips: ' . $ret);
        })->daily();

        $schedule->call(function () {
            // $this->scheduleDriverTrips();
            $this->deleteAccounts();
            $this->endTrips();
            $this->publishTrips();
            $this->assignStudentsToTrips();
        })->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
