<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\PlannedTrip;
use App\Models\PlannedTripDetail;
use App\Models\Trip;
use DB;
use App\Models\Setting;
use App\Models\StudentTrip;
use App\Models\StudentSetting;

use App\Models\RouteStop;
use App\Models\Route;
use App\Models\Stop;
use App\Models\RouteStopDirection;

use App\Models\TripDetail;
use App\Models\User;
use App\Models\Consumption;
trait TripUtils {

    private function scheduleDriverTrips()
    {
        //get mode
        $mode = Setting::where('id', 1)->first()->simple_mode;
        Log::info('Mode is ' . ($mode == 1 ? 'Simple' : 'Advanced'));
        if($mode == 0)
        {
            return;
        }
        //get all drivers
        $drivers = User::where('role_id', 3)->get();
        foreach ($drivers as $driver) {
            //get all trips for the driver
            $this->scheduleDriverTrip($driver->id, 1);
            $this->scheduleDriverTrip($driver->id, 0);
        }
    }

    private function isSchoolOffToday($schoolSettings, $todayDayOfWeek)
    {
        if($todayDayOfWeek == 0)
        {
            return $schoolSettings->sunday;
        }
        else if($todayDayOfWeek == 1)
        {
            return $schoolSettings->monday;
        }
        else if($todayDayOfWeek == 2)
        {
            return $schoolSettings->tuesday;
        }
        else if($todayDayOfWeek == 3)
        {
            return $schoolSettings->wednesday;
        }
        else if($todayDayOfWeek == 4)
        {
            return $schoolSettings->thursday;
        }
        else if($todayDayOfWeek == 5)
        {
            return $schoolSettings->friday;
        }
        else if($todayDayOfWeek == 6)
        {
            return $schoolSettings->saturday;
        }
    }

    private function scheduleDriverTrip($driver_id, $is_morning)
    {
        //get the driver with bus
        $driver = User::with(['bus', 'driverSchool.schoolSettings'])->find($driver_id);

        if ($driver == null) {
            return response()->json(["message" => "Driver not found", "success" => false], 404);
        }

        if ($driver->bus == null) {
            return response()->json(["message" => "Driver does not have a bus", "success" => false], 404);
        }
        $today = Carbon::today();
        // get all pickups for morning and drop-off for afternoon for all students in the school of the driver
        $school_id = $driver->school_id;
        $school = User::with(['schoolStudents.studentSettings', 'schoolSettings'])->find($school_id);

        if ($school == null) {
            return response()->json(["message" => "School not found", "success" => false], 404);
        }

        //check schoolSettings for off day
        $todayDayOfWeek = $today->dayOfWeek;
        if($school->schoolSettings != null && $this->isSchoolOffToday($school->schoolSettings, $todayDayOfWeek))
        {
            Log::info("School is off today");
            return response()->json(["message" => "School is off today", "success" => false], 404);
        }

        $students = $school->schoolStudents;
        //filter students with settings matching the driver's bus
        $students = $students->filter(function ($student) use ($driver, $is_morning, $today) {
            if($student->studentSettings == null)
            {
                return false;
            }
            if($student->studentSettings->absent_on != null && $student->studentSettings->absent_on == $today)
            {
                return false;
            }
            if ($is_morning == 1) {
                return $student->studentSettings->morning_bus_id == $driver->bus->id;
            }
            else
            {
                return $student->studentSettings->afternoon_bus_id == $driver->bus->id;
            }
        });
        if($students->count() == 0)
        {
            Log::info("No students found for the driver's bus");
            return response()->json(["message" => "No students found for the driver's bus", "success" => false], 404);
        }
        $stops = $students->map(function ($student) use ($is_morning) {
            if ($is_morning == 1) {
                return [
                    'lat' => $student->studentSettings->pickup_lat,
                    'lng' => $student->studentSettings->pickup_lng,
                    'address' => $student->studentSettings->pickup_address,
                    'place_id' => $student->studentSettings->pickup_place_id,
                ];
            } else {
                return [
                    'lat' => $student->studentSettings->drop_off_lat,
                    'lng' => $student->studentSettings->drop_off_lng,
                    'address' => $student->studentSettings->drop_off_address,
                    'place_id' => $student->studentSettings->drop_off_place_id,
                ];
            }
        });

        //remove stops with the lat or lng is null
        $stops = $stops->filter(function ($stop) {
            return $stop['lat'] != null && $stop['lng'] != null;
        });

        if($stops->count() == 0)
        {
            Log::info("No stops found for the driver's bus");
            return response()->json(["message" => "No stops found for the driver's bus", "success" => false], 404);
        }

        $stops = $stops->map(function ($stop, $index) use($students) {
            $stop['students'] = [$students->get($index)];
            return $stop;
        });

        //merge stops with the same lat and lng
        $stops = $stops->groupBy(function ($item) {
            return $item['lat'] . '-' . $item['lng'];
        });

        $stops = $stops->map(function ($group) {
            $students = [];
            foreach ($group as $item) {
                $students = array_merge($students, $item['students']);
            }
            return [
                'lat' => $group[0]['lat'],
                'lng' => $group[0]['lng'],
                'address' => $group[0]['address'],
                'place_id' => $group[0]['place_id'],
                'students' => $students,
            ];
        });

        //flatten the stops
        $stops = $stops->values();

        $school_location = [
            'lat' => $school->schoolSettings->lat,
            'lng' => $school->schoolSettings->lng,
            'address' => $school->schoolSettings->address,
            'place_id' => $school->schoolSettings->place_id,
        ];
        //order stops based on the closest to the school location
        $stops = $stops->sortBy(function ($stop) use ($school_location) {
            $distance = $this->distance($stop['lat'], $stop['lng'], $school_location['lat'], $school_location['lng']);
            return $distance;
        });
        $stops = $stops->values();
        if($is_morning == 1)
        {
            //reverse the stops
            $stops = $stops->reverse();
            //add school location to the stops at the end
            $stops->push($school_location);
        }
        else
        {
            //add school location to the stops at the beginning
            $stops->prepend($school_location);
        }

        // Log::info("stops");
        // Log::info($stops);
        // return;
        $route_name = $driver->name . " - " . ($is_morning == 1 ? "Morning" : "Afternoon");

        //add place id from geocoded_waypoints to stops
        $stops = $stops->map(function ($stop, $index) use ($students, $school) {
            $stop['place_id'] = '';
            $stop_name = '';
            if(array_key_exists('students', $stop) && count($stop['students']) > 0)
            {
                $stop_name = $stop['students'][0]->name . " Stop";
            }
            else
            {
                $stop_name = "School ". $school->name . " Stop";
            }
            $stop['name'] = $stop_name;
            return $stop;
        });
        $stops = $stops->values();
        // Log::info("stops");
        // Log::info($stops);

        DB::beginTransaction();
        try {
            $routeDetails = [
                'name' => $route_name,
                'is_morning' => $is_morning, //1 - morning, 0 - afternoon
                'school_id' => $school_id,
            ];

            //create new route
            $savedRoute = Route::create($routeDetails);

            $allStops = [];
            $order = 1;
            for ($i=0; $i < count($stops); $i++) {
                $stop = $stops[$i];
                $newStop = [
                    'name' => $stop['name'],
                    'lat' => $stop['lat'],
                    'lng' => $stop['lng'],
                    'address' => $stop['address'],
                    'place_id' => $stop['place_id'],
                    'school_id' => $school_id,
                ];
                //create the stop
                $savedStop = Stop::create($newStop);
                array_push($allStops, $savedStop);
                $savedStopId = $savedStop->id;
                $routeStop = [
                    'stop_id' => $savedStopId,
                    'route_id' => $savedRoute->id,
                    'order' => $order,
                ];
                $savedRouteStop = RouteStop::create($routeStop);
                if($i > 0)
                {
                    $path = [
                        [
                            'lat' => $allStops[$i-1]->lat,
                            'lng' => $allStops[$i-1]->lng
                        ],
                        [
                            'lat' => $allStops[$i]->lat,
                            'lng' => $allStops[$i]->lng
                        ]
                    ];
                    //loop through ordered directions and save them to the database
                    $direction = '';
                    $directionDetails = [
                        'route_stop_id' => $savedRouteStop->id,
                        'summary' => "Leg " . $i,
                        'index' => $i-1,
                        'overview_path' => json_encode($path),
                        'current' => 1,
                    ];
                    RouteStopDirection::create($directionDetails);
                }
                $order = $order + 1;
            }
            //create a trip on the route
            $todayDate = Carbon::now()->toDateString();
            $nowTime = Carbon::now()->toTimeString();
            $tripDetails = [
                'channel' => uniqid(),
                'driver_id' => $driver_id,
                'bus_id' => $driver->bus_id,
                'route_id' => $savedRoute->id,
                'stop_to_stop_avg_time' => 0,
                'effective_date' => $todayDate,
                'repetition_period' => 0,
                'first_stop_time' => '00:00:00',
                'school_id' => $school_id,
                'status_id' => 1,
                'last_stop_time' => '00:00:00',
            ];
            $savedTrip = Trip::create($tripDetails);
            $tripID = $savedTrip->id;

            $lastStopTime = 0;
            for ($i = 0; $i < count($allStops); $i++) {
                $stop_id = $allStops[$i]->id;
                $tripDetail = [
                    'stop_id' => $stop_id,
                    'planned_timestamp' => '00:00:00',
                    'inter_time' => 0,
                    'trip_id' => $tripID
                ];
                TripDetail::create($tripDetail);
            }

            //update students settings
            for($i = 0; $i < count($stops); $i++)
            {
                //check if ordered_stops[$i] contains student_index
                if(array_key_exists('students', $stops[$i]) == false)
                    continue;
                $students = $stops[$i]['students'];
                $stop_id = $allStops[$i]->id;
                foreach ($students as $student) {
                    Log::info("assigned stop " . $allStops[$i] . " to student " . $student->id);
                    $studentSettings = $student->studentSettings;
                    if($is_morning)
                    {
                        $studentSettings->pickup_trip_id = $tripID;
                        $pickup_route_stop = RouteStop::where('route_id', $savedRoute->id)->where('stop_id', $stop_id)->first();
                        $studentSettings->pickup_route_stop_id = $pickup_route_stop->id;
                    }
                    else
                    {
                        $studentSettings->drop_off_trip_id = $tripID;
                        $drop_off_route_stop = RouteStop::where('route_id', $savedRoute->id)->where('stop_id', $stop_id)->first();
                        $studentSettings->drop_off_route_stop_id = $drop_off_route_stop->id;
                    }

                    $studentSettings->save();
                }
            }
            //save
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('Error in scheduleDriverTrip ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() . ', '. $e->getFile() . ', '. $e->getLine()], 422);
        }
    }

    private function getAvailableSeatsForTrip($tripId, $pickup)
    {
        //get trip, then driver of the trip, then bus of the driver
        $trip = Trip::with(['driver.bus'])->find($tripId);
        $bus = $trip->driver->bus;
        $capacity = $bus->capacity;

        // count all students in student_settings that has pickup_trip_id = $tripId
        $pickupStudents = StudentSetting::where('pickup_trip_id', $tripId)->get();
        $pickupStudentsCount = count($pickupStudents);

        // count all students in student_settings that has drop_off_trip_id = $tripId
        $dropOffStudents = StudentSetting::where('drop_off_trip_id', $tripId)->get();
        $dropOffStudentsCount = count($dropOffStudents);

        if($pickup)
        {
            return $capacity - $pickupStudentsCount;
        }
        else
        {
            return $capacity - $dropOffStudentsCount;
        }
    }

    public function getPickupDropOffTripForStudent($student_id)
    {
        //get student settings
        $studentSettings = StudentSetting::where('student_id', $student_id)->first();
        if($studentSettings)
        {
            //get pickup trip
            $pickup_route_stop_id = $studentSettings->pickup_route_stop_id;
            $pickupTripId = $studentSettings->pickup_trip_id;

            // get route and stop
            $routeStop = RouteStop::find($pickup_route_stop_id);
            if($routeStop != null)
            {
                $route_id = $routeStop->route_id;
                $startPickupStopId = $routeStop->stop_id;
                //get last stop of the route
                $endPickupStop = RouteStop::where('route_id', $route_id)->orderBy('order', 'desc')->first();
                $endPickupStopId = $endPickupStop->stop_id;
            }
            else
            {
                Log::info('Student ' . $student_id . ' has no route stop for pickup');
                $startPickupStopId = null;
                $endPickupStopId = null;
            }
            //get drop off trip
            $drop_off_route_stop_id = $studentSettings->drop_off_route_stop_id;
            $dropOffTripId = $studentSettings->drop_off_trip_id;
            // get route and stop
            $routeStop = RouteStop::find($drop_off_route_stop_id);
            if($routeStop != null)
            {
                $route_id = $routeStop->route_id;
                $endDropOffStopId = $routeStop->stop_id;

                //get last stop of the route
                $startDropOffStop = RouteStop::where('route_id', $route_id)->orderBy('order', 'asc')->first();
                $startDropOffStopId = $startDropOffStop->stop_id;
            }
            if(!$routeStop)
            {
                Log::info('Student ' . $student_id . ' has no route stop for drop off');
                $startDropOffStopId = null;
                $endDropOffStopId = null;
            }

            return array(
                $pickupTripId,
                $startPickupStopId,
                $endPickupStopId,
                $dropOffTripId,
                $startDropOffStopId,
                $endDropOffStopId,
            );
        }
        else
        {
            Log::info('Student ' . $student_id . ' has no settings');
        }
        return null;
    }


    public function assignStudentsToTrips()
    {
        // get all students
        $students = User::with(['studentSchool', 'studentGuardians.guardian'])->where('role_id', 6)->get();
        $today = Carbon::today();
        foreach ($students as $student) {
            if($student->studentSchool == null)
            {
                Log::info('Student ' . $student->id . ' has no school');
                continue;
            }
            // get student settings
            $studentSettings = StudentSetting::where('student_id', $student->id)->first();
            if(!$studentSettings)
            {
                Log::info('Student ' . $student->id . ' has no settings');
                continue;
            }
            //if student is absent, continue
            if($studentSettings->absent_on != null && $studentSettings->absent_on == $today)
            {
                Log::info('Student ' . $student->id . ' is absent today');
                continue;
            }
            if($studentSettings->absent_on != null && $studentSettings->absent_on < $today)
            {
                Log::info('Student ' . $student->id . ' was absent on ' . $studentSettings->absent_on);
                //set absent_on to null
                $studentSettings->absent_on = null;
                $studentSettings->save();
            }
            Log::info('Student ' . $student->id . ' has settings. Pickup trip ' . $studentSettings->pickup_trip_id . ' Drop off trip ' . $studentSettings->drop_off_trip_id);
            $canReserve = false;
            // update balance for student's school
            if($student->studentSchool->balance > 0)
            {
                $canReserve = true;
            }
            else
            {
                Log::info('Student ' . $student->id . ' has no balance in his school');
                $studentParent = $student->studentGuardians->where('guardian.role_id', 4)->first();
                if($studentParent == null)
                {
                    Log::info('Student ' . $student->id . ' has no parent');
                    continue;
                }
                $studentParent = $studentParent->guardian;

                Log::info('Student ' . $student->id . ' has parent ' . $studentParent->id . ' with balance ' . $studentParent->balance);
                //check his parent balance
                if($studentParent->balance > 0)
                {
                    $canReserve = true;
                }
            }
            if(!$canReserve)
            {
                Log::info('Student ' . $student->id . ' has no balance in his school or his parent has no balance');
                //change status to out_of_coins
                $student->status_id = 5;
                $student->save();
                continue;
            }
            else
            {
                Log::info('Student ' . $student->id . ' has balance');
                if($student->status_id == 5)
                {
                    //change status to active
                    $student->status_id = 1;
                    $student->save();
                }
            }
            // get getPickupDropOffTripForStudent
            $trips = $this->getPickupDropOffTripForStudent($student->id);
            if($trips)
            {
                // //log trips
                Log::info('Student ' . $student->id . ' has trips ' . $trips[0] . ' ' . $trips[1] . ' ' . $trips[2] . ' ' . $trips[3] . ' ' . $trips[4] . ' ' . $trips[5]);

                $pickupTripId = $trips[0];
                $startPickupStopId = $trips[1];
                $endPickupStopId = $trips[2];
                $dropOffTripId = $trips[3];
                $startDropOffStopId = $trips[4];
                $endDropOffStopId = $trips[5];

                //get pickup trip
                $pickupPlannedTrip = PlannedTrip::where('trip_id', $pickupTripId)->where('planned_date', $today)->first();

                if($pickupPlannedTrip != null)
                {
                    $pickupPlannedTripDetails = $pickupPlannedTrip->plannedTripDetail;
                    $pickupPlannedStartTripDetails = $pickupPlannedTripDetails->filter(function ($plannedTripDetail) use ($startPickupStopId) {
                        return $plannedTripDetail->stop_id == $startPickupStopId;
                    })->first();
                }
                else
                {
                    Log::info('No planned trip for trip ' . $pickupTripId . ' on ' . $today);
                    $pickupPlannedStartTripDetails = null;
                }

                //get drop off trip
                $dropOffPlannedTrip = PlannedTrip::where('trip_id', $dropOffTripId)->where('planned_date', $today)->first();

                if($dropOffPlannedTrip != null)
                {
                    $dropOffPlannedTripDetails = $dropOffPlannedTrip->plannedTripDetail;
                    $dropOffPlannedStartTripDetails = $dropOffPlannedTripDetails->filter(function ($plannedTripDetail) use ($startDropOffStopId) {
                        return $plannedTripDetail->stop_id == $startDropOffStopId;
                    })->first();
                }
                else
                {
                    Log::info('No planned trip for trip ' . $dropOffTripId . ' on ' . $today);
                    $dropOffPlannedStartTripDetails = null;
                }

                //begin transaction
                DB::beginTransaction();
                try {
                    $createPickupTrip = false;
                    $createDropOffTrip = false;
                    if($pickupPlannedTrip != null && $pickupPlannedStartTripDetails != null && $startPickupStopId != null && $endPickupStopId != null)
                    {
                        //create student trip for pickup trip
                        $pickupStudentTrip = StudentTrip::where('student_id', $student->id)->where('planned_trip_id', $pickupPlannedTrip->id)->first();

                        if($pickupStudentTrip == null)
                        {
                            $createPickupTrip = true;
                            Log::info('Creating student trip for student ' . $student->id . ' on trip ' . $pickupPlannedTrip->id . ' on stop ' . $startPickupStopId . ' to stop ' . $endPickupStopId);
                            StudentTrip::create([
                                'student_id' => $student->id,
                                'planned_trip_id' => $pickupPlannedTrip->id,
                                'riding_date' => $today,
                                'start_stop_id' => $startPickupStopId,
                                'end_stop_id' => $endPickupStopId,
                                'planned_start_time' => $pickupPlannedStartTripDetails->planned_timestamp,
                                'ride_status' => 0,
                            ]);

                        }
                        else
                        {
                            Log::info('Student trip for student ' . $student->id . ' on trip ' . $pickupPlannedTrip->id . ' on stop ' . $startPickupStopId . ' to stop ' . $endPickupStopId . ' already exists');
                        }
                    }


                    if($dropOffPlannedTrip != null && $dropOffPlannedStartTripDetails != null && $startDropOffStopId != null && $endDropOffStopId != null)
                    {
                        //create student trip for drop off trip
                        $dropOffStudentTrip = StudentTrip::where('student_id', $student->id)->where('planned_trip_id', $dropOffPlannedTrip->id)->first();

                        if($dropOffStudentTrip == null)
                        {
                            $createDropOffTrip = true;
                            Log::info('Creating student trip for student ' . $student->id . ' on trip ' . $dropOffPlannedTrip->id . ' on stop ' . $startDropOffStopId . ' to stop ' . $endDropOffStopId);
                            StudentTrip::create([
                                'student_id' => $student->id,
                                'planned_trip_id' => $dropOffPlannedTrip->id,
                                'riding_date' => $today,
                                'start_stop_id' => $startDropOffStopId,
                                'end_stop_id' => $endDropOffStopId,
                                'planned_start_time' => $dropOffPlannedStartTripDetails->planned_timestamp,
                                'ride_status' => 0,
                            ]);
                        }
                        else
                        {
                            Log::info('Student trip for student ' . $student->id . ' on trip ' . $dropOffPlannedTrip->id . ' on stop ' . $startDropOffStopId . ' to stop ' . $endDropOffStopId . ' already exists');
                        }
                    }

                    if($createPickupTrip || $createDropOffTrip)
                    {
                        Log::info('Student ' . $student->id . ' has no trip. Creating one');
                        // update balance for student's school
                        if($student->studentSchool->balance > 0)
                        {
                            $student->studentSchool->balance = $student->studentSchool->balance - 1;
                            $student->studentSchool->save();

                            //add Consumption
                            Consumption::create([
                                'user_id' => $student->studentSchool->id,
                                'amount' => 1,
                                'date' => $today,
                            ]);
                        }
                        else
                        {
                            $studentParent = $student->studentGuardians->where('guardian.role_id', 4)->first()->guardian;
                            //check his parent balance
                            if($studentParent->balance > 0)
                            {
                                $studentParent->balance = $studentParent->balance - 1;
                                $studentParent->save();

                                //add Consumption
                                Consumption::create([
                                    'user_id' => $studentParent->id,
                                    'amount' => 1,
                                    'date' => $today,
                                ]);
                            }
                        }
                        //change status to active
                        $student->status_id = 1;
                        $student->save();
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();
                    Log::info('Error in assignStudentsToTrips ' . $e->getMessage());
                }
            }
            else
            {
                Log::info('Student ' . $student->id . ' has no trips');
            }
        }
    }

    public function publishTrips()
    {
        $currentSettings = Setting::where("id", 1)->first();
        $publish_trips_future_days = 0;
        $currentTime = Carbon::now();
        $trips = Trip::with('driver.bus')->where('status_id', 1)->where('driver_id', '!=', null)->get();
        $today = Carbon::today();
        $end = new Carbon($today);
        $end->setTime(23, 59, 59);
        $end->add($publish_trips_future_days, 'days');
        // Log::info('Now: ' . $currentTime. ' Publishing trips from ' . $today->__toString() . ' to ' . $end->__toString());
        $count = 0;
        foreach ($trips as $trip) {
            $startDate = clone $today;
            $endDate = clone $end;
            list($startCal, $events) = $this->getAllEvents($trip, $startDate, $endDate);
            if(count($events) > 0)
            {
                foreach ($events as $event) {
                    if($event['status'] == 1)
                    {
                        //create transaction
                        DB::beginTransaction();
                        try {
                            $plannedTrip = PlannedTrip::insertOrIgnore([
                                'channel' => $trip->channel,
                                'trip_id' => $trip->id,
                                'route_id' => $trip->route_id,
                                'planned_date' => $event['start'],
                                'driver_id' => $trip->driver_id,
                                'bus_id' => $trip->driver->bus->id,
                            ]);
                            //get all stops for this trip
                            $tripDetails = $trip->tripDetails()->get();
                            //save PlannedTripDetails
                            foreach ($tripDetails as $tripDetail) {
                                $pp = PlannedTripDetail::create([
                                    'planned_trip_id' => $plannedTrip->id,
                                    'stop_id' => $tripDetail->stop_id,
                                    'planned_timestamp' => $tripDetail->planned_timestamp,
                                ]);
                            }
                            $count++;
                            DB::commit();
                        } catch (\Exception $e) {
                            Log::info('Error in publishTrips ' . $e->getMessage() . ' ' . $e->getLine());
                            DB::rollback();
                        }
                    }
                }
            }
        }
        Log::info('Published ' . $count . ' trips');


        //delete published trips that was old and has no reservations
        // get current date - 1 days
        $dateBefore = Carbon::today();
        $dateBefore->subDays(1);

        // get all PlannedTrip that has no reservations and planned_date is less than $dateBefore
        $oldTrips = PlannedTrip::with('reservations')->where('planned_date', '<', $dateBefore)->get();
        $toBeDeletedTripsIds = [];
        foreach ($oldTrips as $oldTrip) {
            //count the reservations
            if(count($oldTrip->reservations) == 0)
            {
                array_push($toBeDeletedTripsIds, $oldTrip->id);
            }
        }
        //delete toBeDeletedTripsIds in DB using Where in
        Log::info('Deleting trips before ' . $dateBefore->__toString() .' that has no reservations with total count of ' . count($toBeDeletedTripsIds));
        // delete trip_search_results that has planned_trip_id in $toBeDeletedTripsIds
        PlannedTrip::whereIn('id', $toBeDeletedTripsIds)->delete();

        //get all planned trips without a driver or without a bus
        $plannedTripsIds = PlannedTrip::where('driver_id', null)->orWhere('bus_id', null)->pluck('id');
        //delete all
        Log::info('count($plannedTripsIds) '. count($plannedTripsIds) .'');
        PlannedTrip::whereIn('id', $plannedTripsIds)->delete();
    }

    private function getAllEvents($trip, $start, $end, $suspension_id = null)
    {
        //Log::info("Start ". $start);
        $effective_date = new Carbon($trip->effective_date);
        $events = [];
        $startCal = null;
        if ($trip->repetition_period == 0) {
            $startCal = $effective_date->__toString();
            $event = $this->generateEvent($trip, $effective_date, $suspension_id);
            array_push($events, $event);
        } else if ($end > $effective_date) {
            //Log::info('Generating events for trip ' . $trip->id);
            $timestamp = $start;
            if ($effective_date > $start)
                $timestamp = $effective_date;

            //Log::info('Start date is ' . $timestamp->__toString());
            $timestamp = $timestamp->setTimeFromTimeString($trip->first_stop_time);
            while ($timestamp <= $end) {
                $event = $this->generateEvent($trip, $timestamp, $suspension_id);
                array_push($events, $event);
                $timestamp->add($trip->repetition_period, 'days');
                //Log::info('Adding ' . $trip->repetition_period . ' days to ' . $timestamp->__toString());
            }
        }


        return array($startCal, $events);
    }

    private function generateEvent($trip, $timestamp, $suspension_id = null)
    {
        $eventColor = "secondary";

        $eventDriver = $trip->driver;
        $status = 1;
        if ($eventDriver) {
            $eventColor = "success";
        }

        $suspension_id = $this->checkSuspendedTrip($trip, $timestamp, $suspension_id);
        if ($suspension_id) {
            $eventColor = "error";
            $status = 0;
        }

        $event = [
            'start' => $timestamp->__toString(),
            'color' => $eventColor,
            'timed' => true,
            'driver' => $eventDriver,
            'status' => $status,
            'suspension_id' => $suspension_id,
        ];

        return $event;
    }

    public function checkSuspendedTrip($trip, $date, $suspension_id = null)
    {

        $suspensions = $trip->suspensions;
        $found_suspension_id = null;
        if ($suspensions) {
            foreach ($suspensions as $key => $suspension) {
                $suspend_date = new Carbon($suspension->date);
                $repetition = $suspension->repetition_period;

                if ($date >= $suspend_date) {
                    $diff = $date->diffInDays($suspend_date);
                    if (($repetition == 0 && $diff == 0) || (($repetition != 0) && (($diff % $repetition) == 0))) {
                        $found_suspension_id = $suspension->id;
                    }
                }
                if ($suspension_id != null) {
                    if ($suspension_id == $found_suspension_id) {
                        break;
                    } else {
                        $found_suspension_id = null;
                    }
                }
            }
        }
        return $found_suspension_id;
    }

    //Calculate the distance between two points in km's using lat and lng
    public function distance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return ($miles * 1.609344);
    }
}
