<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repository\StopRepositoryInterface;
use App\Repository\RouteRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\StudentSettingRepositoryInterface;
use App\Repository\RouteStopRepositoryInterface;
use App\Repository\TripRepositoryInterface;
use App\Repository\SettingRepositoryInterface;
use DB;
use App\Traits\UserUtils;
use App\Traits\TripUtils;
use Illuminate\Support\Facades\Log;
class StopController extends Controller
{
    use UserUtils;
    use TripUtils;
    //
    private $stopRepository;
    private $routeRepository;
    private $userRepository;
    private $studentSettingRepository;
    private $routeStopRepository;
    private $tripRepository;
    private $settingRepository;
    public function __construct(
        StopRepositoryInterface $stopRepository,
        RouteRepositoryInterface $routeRepository,
        UserRepositoryInterface $userRepository,
        StudentSettingRepositoryInterface $studentSettingRepository,
        RouteStopRepositoryInterface $routeStopRepository,
        SettingRepositoryInterface $settingRepository,
        TripRepositoryInterface $tripRepository)
    {
        $this->stopRepository = $stopRepository;
        $this->routeRepository = $routeRepository;
        $this->userRepository = $userRepository;
        $this->studentSettingRepository = $studentSettingRepository;
        $this->routeStopRepository = $routeStopRepository;
        $this->tripRepository = $tripRepository;
        $this->settingRepository = $settingRepository;
    }

    public function index()
    {
        $authUser = auth()->user();
        //check if school
        if($authUser->role_id == 2)
        {
            return response()->json($this->stopRepository->allWhere(['*'], ['routes'], [['school_id', '=', $authUser->id]]), 200);
        }
        //get all stops
        return response()->json($this->stopRepository->all(['*'], ['routes']), 200);
    }

    public function getStop($stop_id)
    {
        //get stop by id
        return response()->json($this->stopRepository->findById($stop_id), 200);
    }

    public function createEdit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'stop' => 'required',
            'stop.id' => 'integer|nullable',
            'stop.name' => 'required|string',
            'stop.address' => 'required|string',
            'stop.place_id' => 'required|string',
            'stop.lat' => 'required|numeric',
            'stop.lng' => 'required|numeric',
        ], [], []);

        $update = false;
        $stop_id = null;
        $stop = $request->stop;
        $auth_user_id = auth()->user()->id;
        if(array_key_exists('id', $stop) && $stop['id'] != null)
        {
            $update = true;
            $stop_id = $stop['id'];
            //check if the user can view the stop
            if(!$this->canViewStop($stop_id, $auth_user_id))
            {
                return response()->json(['message' => 'You are not authorized to edit this stop'], 403);
            }
        }
        if($update)
        {
            //update the stop data
            $this->stopRepository->update($stop_id, $stop);
            return response()->json(['success' => ['stop updated successfully']]);
        }
        else
        {
            //add school id to the stop
            $stop['school_id'] = $auth_user_id;
            //create new stop
            $this->stopRepository->create($stop);
            return response()->json(['success' => ['stop created successfully']]);
        }
    }

    //destroy stop
    public function destroy($stop_id)
    {
        //check if the user can view the stop
        if(!$this->canViewStop($stop_id, auth()->user()->id))
        {
            return response()->json(['message' => 'You are not authorized to delete this stop'], 403);
        }
        //check if the stop has routes
        $stop = $this->stopRepository->findById($stop_id, ['*'], ['routes']);
        if(count($stop->routes) > 0)
        {
            return response()->json(['message' => 'The stop has routes, you can not delete it'], 400);
        }
        //delete the stop
        $this->stopRepository->deleteById($stop_id);
        return response()->json(['success' => ['stop deleted successfully']]);
    }

    //getClosestStops
    public function getClosestStops(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lang' => 'required|numeric',
            'pick_up' => 'required|numeric',
        ], [], []);

        $lat = $request->lat;
        $lng = $request->lang;
        $pick_up = $request->pick_up;
        $student_id = $request->student_id;

        //get school id of the student
        $student = $this->userRepository->findById($student_id);
        if($student == null)
        {
            return response()->json(['message' => 'The student does not exist'], 400);
        }
        $school_id = $student->school_id;
        if($school_id == null)
        {
            return response()->json(['message' => 'The student does not belong to a school'], 400);
        }

        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();

        $currentTrip = null;
        if($studentSetting != null)
        {
            //get student current trip
            if($pick_up)
            {
                $currentTrip = $studentSetting->pickup_trip_id;
            }
            else
            {
                $currentTrip = $studentSetting->drop_off_trip_id;
            }
        }

        $settings = $this->settingRepository->all()->first();
        //get max_search_radius
        $max_search_radius = $settings->max_search_radius;
        //get all morning routes
        $routes = $this->routeRepository->findByWhere(['is_morning' => $pick_up, 'school_id' => $school_id],
        ['*'], ['stops', 'trips']);
        $stops = [];
        foreach($routes as $route)
        {
            $trips = $route->trips;
            //check if one of the trips is not trashed
            $trips = $trips->filter(function($trip) {
                return $trip->status_id != 3;
            });
            if(count($trips) == 0)
            {
                continue;
            }

            foreach($route->stops as $stop)
            {
                $distance = $this->distance($lat, $lng, $stop->lat, $stop->lng)*1000; //convert to meters
                if($distance <= $max_search_radius)
                {
                    $stop_details = $this->getStopDetails($route->id, $stop->id);
                    $pick_times = [];
                    $drop_times = [];
                    $routes_ids = [];
                    $trips_ids = [];
                    $routes_of_stop = [];
                    $available_seats = [];
                    foreach($stop_details as $stop_detail)
                    {
                        $trip_id = $stop_detail['trip_id'];
                        $availableSeats = $this->getAvailableSeatsForTrip($trip_id, $pick_up);
                        if($trip_id == $currentTrip)
                        {
                            $availableSeats = $availableSeats + 1;
                        }
                        $available_seats[] = $availableSeats;
                        $pick_times[] = $stop_detail['pick_time'];
                        $drop_times[] = $stop_detail['drop_time'];
                        $routes_ids[] = $stop_detail['route_id'];
                        $trips_ids[] = $stop_detail['trip_id'];
                        $routes_of_stop[] = $stop_detail['route'];

                        // $pick_times[] = $stop_detail['pick_time'];
                        // $drop_times[] = $stop_detail['drop_time'];
                        // $routes_ids[] = $stop_detail['route_id']+1;
                        // $trips_ids[] = $stop_detail['trip_id']+1;
                        // $routes_of_stop[] = $stop_detail['route'];

                        // $pick_times[] = $stop_detail['pick_time'];
                        // $drop_times[] = $stop_detail['drop_time'];
                        // $routes_ids[] = $stop_detail['route_id']+2;
                        // $trips_ids[] = $stop_detail['trip_id']+2;
                        // $routes_of_stop[] = $stop_detail['route'];
                    }
                    $stops[] = [
                        'id' => $stop->id,
                        'name' => $stop->name,
                        'address' => $stop->address,
                        'lat' => $stop->lat,
                        'lng' => $stop->lng,
                        'distance' => $distance,
                        'pick_times' => $pick_times,
                        'drop_times' => $drop_times,
                        'routes_ids' => $routes_ids,
                        'trips_ids' => $trips_ids,
                        'routes' => $routes_of_stop,
                        'available_seats' => $available_seats,
                    ];
                }
            }
        }
        //Log::info('stops: '.json_encode($stops));
        return response()->json(['stops' => $stops], 200);
    }

    private function getStopDetails($route_id, $stop_id)
    {
        $route = $this->routeRepository->findById($route_id, ['*'], ['trips.tripDetails', 'trips.driver.bus']);
        $trips = $route->trips;
        $stop_details = [];
        foreach($trips as $trip)
        {
            //check if trip is trashed
            if($trip->status_id == 3)
            {
                continue;
            }

            //check if trip has a driver
            if($trip->driver == null)
            {
                continue;
            }
            //check if trip has a bus
            if($trip->driver->bus == null)
            {
                continue;
            }
            // get last stop in trip
            if($route->is_morning)
            {
                $dropStopTripDetail = $trip->tripDetails->last();
            }
            else
            {
                $pickStopTripDetail = $trip->tripDetails->first();
            }
            foreach($trip->tripDetails as $tripDetail)
            {
                if($tripDetail->stop_id == $stop_id)
                {
                    if($route->is_morning)
                    {
                        $pickStopTripDetail = $tripDetail;
                    }
                    else
                    {
                        $dropStopTripDetail = $tripDetail;
                    }
                    $stop_details[] = [
                        'pick_time' => $pickStopTripDetail->planned_timestamp,
                        'drop_time' => $dropStopTripDetail->planned_timestamp,
                        'route_id' => $route_id,
                        'trip_id' => $trip->id,
                        'route' => $route,
                    ];
                }
            }
        }
        return $stop_details;
    }

    //setPickupDropOff
    public function setPickupDropOff(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
            'stop_id' => 'required|integer',
            'route_id' => 'required|integer',
            'trip_id' => 'required|integer',
            'pick_up' => 'required|boolean',
        ], [], []);

        $student_id = $request->student_id;
        $stop_id = $request->stop_id;
        $route_id = $request->route_id;
        $trip_id = $request->trip_id;
        $pick_up = $request->pick_up;

        //get auth user id
        $auth_user = auth()->user();
        $parent = $this->userRepository->findById($auth_user->id, ['*'], ['guardianStudents.student']);
        //check if the student belongs to the parent
        $student = $parent->guardianStudents->where('student_id', $student_id)->first()->student;
        if($student == null)
        {
            return response()->json(['message' => 'The student does not belong to you'], 400);
        }

        //check if the stop is in the route
        $route = $this->routeRepository->findById($route_id, ['*'], ['stops']);
        $stop = $route->stops->where('id', $stop_id)->first();
        if($stop == null)
        {
            return response()->json(['message' => 'The stop is not in the route'], 400);
        }

        //check if the trip is in the route
        $trip = $route->trips->where('id', $trip_id)->first();
        if($trip == null)
        {
            return response()->json(['message' => 'The trip is not in the route'], 400);
        }

        //check if the stop is in the trip
        $tripDetail = $trip->tripDetails->where('stop_id', $stop_id)->first();
        if($tripDetail == null)
        {
            return response()->json(['message' => 'The stop is not in the trip'], 400);
        }

        //check if student is in the school of the stop, route and trip
        if($student->school_id != $route->school_id || $student->school_id != $stop->school_id || $student->school_id != $trip->school_id)
        {
            return response()->json(['message' => 'The student is not in the school of the stop, route or trip'], 400);
        }

        //get route_stop_id
        $route_stop_id = $this->routeStopRepository->findByWhere(['route_id' => $route_id, 'stop_id' => $stop_id])->first()->id;

        //get the student setting
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();

        $currentTrip = null;
        if($studentSetting != null)
        {
            //get student current trip
            if($pick_up)
            {
                $currentTrip = $studentSetting->pickup_trip_id;
            }
            else
            {
                $currentTrip = $studentSetting->drop_off_trip_id;
            }
        }

        //check the available seats
        $availableSeats = $this->getAvailableSeatsForTrip($trip_id, $pick_up);
        if($trip_id == $currentTrip)
        {
            $availableSeats = $availableSeats + 1;
        }
        if($availableSeats <= 0)
        {
            Log::info('availableSeats for trip: '. $trip_id . ' is: '. $availableSeats);
            return response()->json(['message' => 'There is no available seats in the trip'], 400);
        }

        if($studentSetting == null)
        {
            //create new student setting
            $this->studentSettingRepository->create([
                'student_id' => $student_id,
                'pickup_route_stop_id' => $pick_up ? $route_stop_id : null,
                'drop_off_route_stop_id' => !$pick_up ? $route_stop_id : null,
                'pickup_trip_id' => $pick_up ? $trip_id : null,
                'drop_off_trip_id' => !$pick_up ? $trip_id : null,
            ]);
        }
        else
        {
            // Log::info('studentSetting: '.json_encode($studentSetting));
            //update the student setting
            if($pick_up)
            {
                $this->studentSettingRepository->update($studentSetting->id, ['pickup_route_stop_id' => $route_stop_id, 'pickup_trip_id' => $trip_id]);
            }
            else
            {
                $this->studentSettingRepository->update($studentSetting->id, ['drop_off_route_stop_id' => $route_stop_id, 'drop_off_trip_id' => $trip_id]);
            }
        }

        $student->student_details = $this->getStudentSettings($student->id);
        return response()->json(['student' => $student]);
    }


    public function setPickupDropOffLocation(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'address' => 'required|string',
            'pick_up' => 'required|boolean',
        ], [], []);

        $student_id = $request->student_id;
        $lat = $request->lat;
        $lng = $request->lng;
        $address = $request->address;
        $pick_up = $request->pick_up;

        //get auth user id
        $auth_user = auth()->user();
        $parent = $this->userRepository->findById($auth_user->id, ['*'], ['guardianStudents.student']);
        //check if the student belongs to the parent
        $student = $parent->guardianStudents->where('student_id', $student_id)->first()->student;
        if($student == null)
        {
            return response()->json(['message' => 'The student does not belong to you'], 400);
        }

        //get the student setting
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();

        if($studentSetting != null)
        {
            if($pick_up)
            {
                $this->studentSettingRepository->update($studentSetting->id, [
                    'pickup_lat' => $lat,
                    'pickup_lng' => $lng,
                    'pickup_address' => $address,
                    'pickup_place_id' => '',
                ]);
            }
            else
            {
                $this->studentSettingRepository->update($studentSetting->id, [
                    'drop_off_lat' => $lat,
                    'drop_off_lng' => $lng,
                    'drop_off_address' => $address,
                    'drop_off_place_id' => '',
                ]);
            }
        }
        else
        {
            //create new student setting
            $this->studentSettingRepository->create([
                'student_id' => $student_id,
                'pickup_lat' => $pick_up ? $lat : null,
                'pickup_lng' => $pick_up ? $lng : null,
                'pickup_address' => $pick_up ? $address : null,
                'pickup_place_id' => $pick_up ? '' : null,
                'drop_off_lat' => !$pick_up ? $lat : null,
                'drop_off_lng' => !$pick_up ? $lng : null,
                'drop_off_address' => !$pick_up ? $address : null,
                'drop_off_place_id' => !$pick_up ? '' : null,
            ]);
        }
        $student->student_details = $this->getStudentSettings($student->id);
        return response()->json(['student' => $student]);
    }
}
