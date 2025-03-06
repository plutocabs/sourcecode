<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repository\RouteRepositoryInterface;
use App\Repository\SuspendedTripRepositoryInterface;
use App\Repository\TripDetailRepositoryInterface;
use App\Repository\TripRepositoryInterface;
use App\Repository\PlannedTripRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\RouteStopRepositoryInterface;
use App\Repository\ReservationRepositoryInterface;
use App\Repository\TripSearchResultRepositoryInterface;
use App\Repository\UserPaymentRepositoryInterface;
use App\Repository\ComplaintRepositoryInterface;
use App\Repository\SettingRepositoryInterface;
use App\Repository\NotificationRepositoryInterface;
use App\Repository\StudentGuardianRepositoryInterface;
use App\Repository\EventRepositoryInterface;
use App\Repository\EventTypeRepositoryInterface;
use App\Repository\StopRepositoryInterface;
use App\Repository\RouteStopDirectionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\DriverUtils;
use App\Traits\TripUtils;
use App\Models\RouteStop;
use App\Models\RouteStopDirection;
use App\Models\Route;
use App\Models\Setting;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use App\Models\TripDetail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use \stdClass;
use DB;
use Validator;
use App\Traits\UserUtils;

//use the PlannedTripDetail model
use App\Models\PlannedTripDetail;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
class TripController extends Controller
{
    use DriverUtils;
    use TripUtils;
    use UserUtils;
    //
    private $tripRepository;
    private $plannedTripRepository;
    private $routeRepository;
    private $tripDetailRepository;
    private $suspendedTripRepository;
    private $userRepository;
    private $routeStopRepository;
    private $reservationRepository;
    private $tripSearchResultRepository;
    private $userPaymentRepository;
    private $complaintRepository;
    private $settingRepository;
    private $messaging;
    private $notificationRepository;
    private $studentGuardianRepository;
    private $eventRepository;
    private $eventTypeRepository;
    private $stopRepository;
    private $routeStopDirectionRepository;

    public function __construct(
        TripRepositoryInterface $tripRepository,
        PlannedTripRepositoryInterface $plannedTripRepository,
        SuspendedTripRepositoryInterface $suspendedTripRepository,
        TripDetailRepositoryInterface $tripDetailRepository,
        RouteRepositoryInterface $routeRepository,
        UserRepositoryInterface $userRepository,
        RouteStopRepositoryInterface $routeStopRepository,
        ReservationRepositoryInterface $reservationRepository,
        TripSearchResultRepositoryInterface $tripSearchResultRepository,
        UserPaymentRepositoryInterface $userPaymentRepository,
        ComplaintRepositoryInterface $complaintRepository,
        SettingRepositoryInterface $settingRepository,
        NotificationRepositoryInterface $notificationRepository,
        Messaging $messaging,
        StudentGuardianRepositoryInterface $studentGuardianRepository,
        EventRepositoryInterface $eventRepository,
        EventTypeRepositoryInterface $eventTypeRepository,
        StopRepositoryInterface $stopRepository,
        RouteStopDirectionRepositoryInterface $routeStopDirectionRepository
    ) {
        $this->tripRepository = $tripRepository;
        $this->plannedTripRepository = $plannedTripRepository;
        $this->tripDetailRepository = $tripDetailRepository;
        $this->routeRepository = $routeRepository;
        $this->suspendedTripRepository = $suspendedTripRepository;
        $this->userRepository = $userRepository;
        $this->routeStopRepository = $routeStopRepository;
        $this->reservationRepository = $reservationRepository;
        $this->tripSearchResultRepository = $tripSearchResultRepository;
        $this->userPaymentRepository = $userPaymentRepository;
        $this->complaintRepository = $complaintRepository;
        $this->settingRepository = $settingRepository;
        $this->notificationRepository = $notificationRepository;
        $this->messaging = $messaging;
        $this->studentGuardianRepository = $studentGuardianRepository;
        $this->eventRepository = $eventRepository;
        $this->eventTypeRepository = $eventTypeRepository;
        $this->stopRepository = $stopRepository;
        $this->routeStopDirectionRepository = $routeStopDirectionRepository;
    }

    public function index()
    {
        $authUser = auth()->user();

        $activeTrips = $this->tripRepository->allWhere(['*'], ['route', 'driver'], [['status_id', '=', 1], ['school_id', '=', $authUser->id]]);

        $trashedTrips = $this->tripRepository->allWhere(['*'], ['route', 'driver'], [['status_id', '=', 3], ['school_id', '=', $authUser->id]]);
        $suspensions = $this->suspendedTripRepository->allWhere(['*'], ['trip', 'trip.route']);

        //remove from suspended trips the trips that are not in the school
        $suspensions = $suspensions->filter(function ($suspension) use ($authUser) {
            return $suspension->trip->school_id == $authUser->id;
        });

        $suspensions = $suspensions->values();

        foreach ($activeTrips as $activeTrip) {
            $plannedTrips = $this->plannedTripRepository->findByWhere(['trip_id' => $activeTrip->id], ['*'], ['plannedTripDetail']);

            if(count($plannedTrips) > 0)
            {
                $stopTimes = [];
                //loop planned trips, get statistics about planned time and actual time of planned trip details
                foreach ($plannedTrips as $plannedTrip) {
                    $planned_trip_details = $plannedTrip->plannedTripDetail;
                    foreach ($planned_trip_details as $planned_trip_detail)
                    {
                        if(!array_key_exists($planned_trip_detail->stop_id, $stopTimes))
                        {
                            $stopTimes[$planned_trip_detail->stop_id] = [
                                'planned_time' => $planned_trip_detail->planned_timestamp,
                                // 'actual_time' => [],
                                'avg_diff' => 0,
                                'sum_diff' => 0,
                                'count' => 0,
                                'stop_name' => $planned_trip_detail->stop!=null ? $planned_trip_detail->stop->name : '',
                            ];
                        }

                        // $stopTimes[$planned_trip_detail->stop_id]['planned_time'][] = $planned_trip_detail->planned_timestamp;
                        // $stopTimes[$planned_trip_detail->stop_id]['actual_time'][] = $planned_trip_detail->actual_timestamp;
                        if($planned_trip_detail->actual_timestamp != null)
                        {
                            //get time difference between planned and actual
                            $planned_time = new Carbon($planned_trip_detail->planned_timestamp);
                            $actual_time = new Carbon($planned_trip_detail->actual_timestamp);
                            $diff = $actual_time->diffInMinutes($planned_time);

                            $stopTimes[$planned_trip_detail->stop_id]['sum_diff'] += $diff;
                            $stopTimes[$planned_trip_detail->stop_id]['count']++;

                            $stopTimes[$planned_trip_detail->stop_id]['avg_diff'] = $stopTimes[$planned_trip_detail->stop_id]['sum_diff'] / $stopTimes[$planned_trip_detail->stop_id]['count'];

                            //approximate the time
                            $stopTimes[$planned_trip_detail->stop_id]['avg_diff'] = round($stopTimes[$planned_trip_detail->stop_id]['avg_diff']);
                        }
                    }

                }
                $activeTrip->stopTimes = $stopTimes;
            }
        }

        return response()->json(
            [
                'activeTrips' => $activeTrips,
                'suspendedTrips' => $suspensions,
                'trashedTrips' => $trashedTrips
            ],
            200
        );
    }

    public function getTrip($trip_id)
    {
        //get trip by id
        return response()->json($this->tripRepository->findById($trip_id, ['*'], ['route', 'driver', 'tripDetails', 'tripDetails.stop']), 200);
    }


    public function checkAssignedDriverTrip($trip)
    {
        return $trip->driver;
    }


    //getAllPlannedTrips
    private function getAllPlannedTrips(Request $request, $mode)
    {
        $school = Auth::user();
        //get all planned trips
        $plannedTrips = $this->plannedTripRepository->allWhere(['*'], ['trip', 'trip.route', 'driver', 'bus', 'reservations']);

        //filter planned trips by school
        $plannedTrips = $plannedTrips->filter(function ($plannedTrip) use ($school) {
            return $plannedTrip->trip->school_id == $school->id;
        });

        $upcomingTrips = [];
        $runningTrips = [];
        $completedTrips = [];
        $passedTrips = [];

        foreach ($plannedTrips as $plannedTrip) {
            $plannedTrip->reservations_count = count($plannedTrip->reservations);
            $plannedStartTime = new Carbon($plannedTrip->plannedTripDetail[0]->planned_timestamp);
            $plannedTrip->planned_start_date_time = $plannedTrip->planned_date . ' ' . $plannedStartTime->hour . ':' . $plannedStartTime->minute . ':' . $plannedStartTime->second;

            //end time
            $plannedEndTime = new Carbon($plannedTrip->plannedTripDetail[count($plannedTrip->plannedTripDetail) - 1]->planned_timestamp);
            $plannedTrip->planned_end_date_time = $plannedTrip->planned_date . ' ' . $plannedEndTime->hour . ':' . $plannedEndTime->minute . ':' . $plannedEndTime->second;

            if ($plannedTrip->started_at == null)
            {
                //check if the trip is passed by comparing the planned start time with the current time. If the planned start time is less than the current time by one day, then the trip is passed
                $plannedStartDateTime = new Carbon($plannedTrip->planned_start_date_time);
                $plannedStartDateTime->add(1, 'day');
                if($plannedStartDateTime < Carbon::now())
                {
                    array_push($passedTrips, $plannedTrip);
                }
                else
                {
                    array_push($upcomingTrips, $plannedTrip);
                }
            }
            else if ($plannedTrip->ended_at == null) {
                //trip is running
                array_push($runningTrips, $plannedTrip);
            } else {
                //trip is completed
                array_push($completedTrips, $plannedTrip);
            }
        }

        //order by planned_start_date_time
        usort($upcomingTrips, function ($a, $b) {
            return $b->planned_start_date_time <=> $a->planned_start_date_time;
        });

        usort($completedTrips, function ($a, $b) {
            return $b->planned_start_date_time <=> $a->planned_start_date_time;
        });

        usort($runningTrips, function ($a, $b) {
            return $b->planned_start_date_time <=> $a->planned_start_date_time;
        });

        usort($passedTrips, function ($a, $b) {
            return $b->planned_start_date_time <=> $a->planned_start_date_time;
        });

        if($mode == 'upcoming')
        {
            return $upcomingTrips;
        }
        else if($mode == 'running')
        {
            return $runningTrips;
        }
        else if($mode == 'completed')
        {
            return $completedTrips;
        }
        else if($mode == 'passed')
        {
            return $passedTrips;
        }
        else
        {
            //return all planned trips
            return array($upcomingTrips, $runningTrips, $completedTrips, $passedTrips);
        }
    }

    //getPlannedTrips
    public function getPlannedTrips(Request $request)
    {
        $upcomingTrips = [];
        $runningTrips = [];
        $completedTrips = [];
        $passedTrips = [];

        list($upcomingTrips, $runningTrips, $completedTrips, $passedTrips) = $this->getAllPlannedTrips($request, 'all');

        return response()->json(['active' => $upcomingTrips, 'completed' => $completedTrips, 'running' => $runningTrips, 'passed' => $passedTrips], 200);
    }

    //getOnRouteTrips
    public function getOnRouteTrips(Request $request)
    {
        $runningTrips = [];

        //get running trips only
        $runningTrips = $this->getAllPlannedTrips($request, 'running');

        return response()->json(['running' => $runningTrips], 200);
    }



    public function getTripsInPeriod(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'trip_id' => 'required|integer',
            'start' => 'required|date',
            'end' => 'required|date',
        ], [], []);

        $trip_id = $request->trip_id;

        $trip = $this->tripRepository->findById($trip_id, ['*'], ['route', 'driver', 'suspensions', 'tripDetails', 'tripDetails.stop']);

        $start = new Carbon($request->start);

        $end = new Carbon($request->end);
        $end->setTime(23, 59, 59);
        $end->add(1, 'months');

        list($startCal, $events) = $this->getAllEvents($trip, $start, $end);

        return response()->json(['events' => $events, 'startCal' => $startCal]);
    }

    public function getTripSuspensions(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'suspension_id' => 'required|integer',
            'trip_id' => 'required|integer',
            'start' => 'required|date',
            'end' => 'required|date',
        ], [], []);

        $suspension_id = $request->suspension_id;
        $trip_id = $request->trip_id;
        $trip = $this->tripRepository->findById($trip_id, ['*'], ['route', 'driver', 'suspensions', 'tripDetails', 'tripDetails.stop']);

        $start = new Carbon($request->start);
        $end = new Carbon($request->end);
        $end->setTime(23, 59, 59);
        $end->add(1, 'months');

        list($startCal, $events) = $this->getAllEvents($trip, $start, $end, $suspension_id);

        $suspension = $this->suspendedTripRepository->findById($suspension_id);
        return response()->json(['events' => $events, 'startCal' => $suspension->date]);
    }

    public function createEdit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'trip' => 'required',
            'action' => 'required|string|in:create,edit,duplicate',
            'trip.id' => 'integer|nullable',
            'trip.route_id' => 'required|integer',
            'trip.repetition_period' => 'required|integer',
            'trip.stop_to_stop_avg_time' => 'required|integer',
            'trip.first_stop_time' => 'required|date_format:H:i',
            'trip.effective_date' => 'required|date',
            'trip.inter_time' => 'required|array',
            'trip.inter_time.*' => 'required|numeric|gte:0',
        ], [], []);

        //check action
        $action = $request->action;
        if ($action == 'edit') {
            if(!$request->trip['id'])
            {
                return response()->json(['errors' => ['Error' => 'Trip id is required']], 422);
            }
        }
        //school
        $school = Auth::user();

        DB::beginTransaction();
        try {
            $route_id = $request->trip['route_id'];
            //check if the route belongs to the school
            if(!$this->canViewRoute($route_id, $school->id))
            {
                return response()->json(['errors' => ['Error' => 'You are not authorized to create a trip for this route']], 422);
            }
            $route = $this->routeRepository->findById($route_id, ['*'], ['stops']);
            //check if the number of stops in the route is equal to the number of inter times
            if (count($request->trip['inter_time']) != count($route->stops)) {
                return response()->json(['errors' => ['Error' => 'Timetable does not match the number of stops in the selected route']], 422);
            }

            $newTripData = [
                'route_id' => $route_id,
                'first_stop_time' => $request->trip['first_stop_time'],
                'status_id' => 1,
                'repetition_period' => $request->trip['repetition_period'],
                'effective_date' => $request->trip['effective_date'],
                'stop_to_stop_avg_time' => $request->trip['stop_to_stop_avg_time'],
            ];

            if($action == 'edit')
            {
                $my_trip_id = $request->trip['id'];
                //check if the trip belongs to the school
                if(!$this->canViewTrip($my_trip_id, $school->id))
                {
                    return response()->json(['errors' => ['Error' => 'You are not authorized to edit this trip']], 422);
                }
                $trip = $this->tripRepository->findById($my_trip_id);
                $newTripData['channel'] = $trip->channel;
                //update trip
                $this->tripRepository->update($trip->id, $newTripData);
                $trip->tripDetails()->delete();
            }
            else if($action == 'create' || $action == 'duplicate')
            {
                //create new trip
                $newTripData['channel'] = uniqid();
                $newTripData['school_id'] = $school->id;
                $trip = $this->tripRepository->create($newTripData);
            }
            $tripID = $trip->id;

            $lastStopTime = 0;
            for ($i = 0; $i < count($request->trip['inter_time']); $i++) {
                $t = $request->trip['inter_time'][$i];
                if ($i == 0)
                    $lastStopTime = $request->trip['first_stop_time'];
                $lastStopTime = strtotime('+' . $t . 'minutes', strtotime($lastStopTime));
                $lastStopTime = date('H:i:s', $lastStopTime);
                $stop_id = $route->stops[$i]->id;
                $tripDetail = [
                    'stop_id' => $stop_id,
                    'planned_timestamp' => $lastStopTime,
                    'inter_time' => $t,
                    'trip_id' => $tripID
                ];
                $this->tripDetailRepository->create($tripDetail);
            }

            //update last_stop_time
            $trip = $this->tripRepository->findById($tripID, ['*'], ['tripDetails']);
            $trip->last_stop_time = $lastStopTime;
            $trip->save();

            // Log::info('Action for trip ' . $trip->id . ' is ' . $action . ' and its data is ' . $trip . ' and its details are ' . $trip->tripDetails);
            //save
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage() . ', ' . $e->getFile() . ', ' . $e->getLine()], 422);
        }

        return response()->json(['success' => ['trip created successfully']]);
    }


    public function trashRestore(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'trip_id' => 'required|integer',
        ], [], []);

        $trip_id = $request->trip_id;
        $school = Auth::user();
        $trip = $this->tripRepository->findByWhere([['id', '=', $trip_id], ['school_id', '=', $school->id]], ['*'], [])->first();
        $trip->status_id = $trip->status_id != 1 ? 1 : 3;
        //$trip->suspend_date = $trip->status_id == 1 ? null : date('Y-m-d');
        $this->tripRepository->update($trip_id, $trip->toArray());
        return response()->json(['success' => ['trip updated successfully']]);
    }

    public function removeSuspension($suspension_id)
    {
        $suspendedTrip = $this->suspendedTripRepository->findByWhere([['id', '=', $suspension_id]], ['*'], ['trip'])->first();
        $school = Auth::user();
        $trip = $this->tripRepository->findByWhere([['id', '=', $suspendedTrip->trip_id], ['school_id', '=', $school->id]], ['*'], [])->first();
        //check if the trip is in the school
        if (!$trip) {
            return response()->json(['error' => ['Trip not found']], 422);
        }
        $this->suspendedTripRepository->deleteById($suspension_id);
    }

    public function suspend(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'trip_id' => 'required|integer',
            'date' => 'date',
            'repetition_period' => 'required|integer|gte:0',
        ], [], []);

        $date = new Carbon($request->date);
        $trip_id = $request->trip_id;
        $school = Auth::user();
        $trip = $this->tripRepository->findByWhere([['id', '=', $trip_id], ['school_id', '=', $school->id]], ['*'], ['driver', 'suspensions'])->first();
        $effective_date = new Carbon($trip->effective_date);

        if ($date < $effective_date) {
            return response()->json(['error' => ['Invalid date']], 422);
        }

        $suspension_id_db = $this->checkSuspendedTrip($trip, $date);
        if ($suspension_id_db) {
            //trip is already suspended
            return response()->json(['error' => ['Trip is already suspended']], 422);
        } else {
            //trip is active, now suspend it
            //check first if it is assigned to a driver
            // $tripDriver = $this->checkAssignedDriverTrip($trip);
            // if ($tripDriver) {
            //     return response()->json(['error' => ['You can not suspend this trip as it is assigned to a driver']]);
            // }
            $suspendednewTripData = [
                'trip_id' => $trip_id,
                'repetition_period' => $request->repetition_period,
                'date' => $date,
            ];
            $suspendedTrip = $this->suspendedTripRepository->create($suspendednewTripData);
            //return suspension_id
            return response()->json(['success' => ['Trip suspended successfully'], 'suspension_id' => $suspendedTrip->id]);
        }
    }

    //un-assign driver from a trip
    public function unassignDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $school = Auth::user();
        $trip = $this->tripRepository->allWhere(['*'], ['driver'], [['id', '=', $request->trip_id], ['school_id', '=', $school->id]])->first();
        if (!$trip) {
            return response()->json(['error' => ['Trip not found']], 422);
        }
        $trip->driver_id = null;
        $this->tripRepository->update($trip->id, $trip->toArray());
        return response()->json(['success' => ['trip updated successfully']]);
    }


    //assign driver to a trip
    public function assignDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|integer',
            'driver_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $school = Auth::user();
        $trip = $this->tripRepository->allWhere(['*'], ['driver'], [['id', '=', $request->trip_id], ['school_id', '=', $school->id]])->first();
        if (!$trip) {
            return response()->json(['error' => ['Trip not found']], 422);
        }
        $driver = $this->userRepository->findByWhere([['id', '=', $request->driver_id], ['role_id', '=', 3], ['school_id', '=', $school->id]], ['*'], ['trips', 'bus'])->first();
        if (!$driver) {
            return response()->json(['error' => ['Driver not found']], 422);
        }
        //$tripDriver = $this->checkAssignedDriverTrip($trip);
        //check if the driver is available for the trip

        // $tripIntersect = $this->isDriverAvailable($driver, $trip);

        // if ($tripIntersect != null && $tripIntersect['x'] != -1) {
        //     return response()->json(['error' => 'Driver is not available for the trip'], 422);
        // }
        //update trip driver
        $trip->driver_id = $driver->id;
        $this->tripRepository->update($trip->id, $trip->toArray());

        //send notification to the driver
        $messageAssignment = "You have been assigned to a trip that will start on " . $trip->effective_date . " at " . $trip->first_stop_time;
        $this->sendNotificationToUser($driver, $messageAssignment);
        return response()->json(['success' => ['trip updated successfully'], 'driver' => $driver]);
    }


    private function getAllRoutesOfStop($stop_id)
    {
        $routeStopsIds = $this->routeStopRepository->findByWhere(['stop_id' => $stop_id], ['route_id'])->pluck('route_id')->toArray();
        $routes = Route::whereIn("id", $routeStopsIds)->get();
        return $routes;
    }
    private function getRoutePath($route_id, $start_stop_id)
    {
        $startRouteStop = $this->routeStopRepository->findByWhere(['route_id' => $route_id, 'stop_id' => $start_stop_id], ['id', 'order'])->first();
        Log::info("startRouteStop");
        Log::info($startRouteStop->order);
        //Get route_stop ids for the route_stop in the route whose ID is route_id and order is greater than the order of the route_stop whose ID is route_stop_id
        $routeStopIds = $this->routeStopRepository->findByWhere([['route_id', $route_id], ['order', '>', $startRouteStop->order]], ['id'])->pluck('id')->toArray();
        Log::info("routeStopIds");
        Log::info($routeStopIds);

        $directions = RouteStopDirection::whereIn("route_stop_id", $routeStopIds)->whereNotNull('overview_path')->where('current', 1)->get();
        $path = [];
        foreach ($directions as $direction) {
            $path = array_merge($path, json_decode($direction->overview_path));
        }
        return $path;
    }

    private function getDistanceBetweenTwoPoints($point1, $point2)
    {
        $earthRadius = 6371000;
        $lat1 = $point1['lat'];
        $lng1 = $point1['lng'];
        $lat2 = $point2['lat'];
        $lng2 = $point2['lng'];
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $dist = $earthRadius * $c;
        return $dist;
    }

    private function isPointWithinRangeOfPath($point, $path, $range)
    {
        $returnData= new stdClass();
        //check if the point is within the range of the path
        $distances = [];
        foreach ($path as $pathPoint) {
            $distance = $this->distance($point['lat'], $point['lng'], $pathPoint->lat, $pathPoint->lng);
            array_push($distances, $distance);
        }
        if(count($distances) == 0){
            $returnData->isWithinRange = false;
            return $returnData;
        }

        if (min($distances) <= $range) {
            $returnData->isWithinRange = true;
            $returnData->distance = min($distances);
            $returnData->pathPointIndex = array_search(min($distances), $distances);
            $returnData->pathPoint = $path[$returnData->pathPointIndex];
            return $returnData;
        } else {
            $returnData->isWithinRange = false;
            return $returnData;
        }
    }

    private function getAvailableSeats($plannedTrip, $startStop, $endStop, $route)
    {
        Log::info("getAvailableSeats for trip ". $plannedTrip->id);
        //count all reservations for the trip from startStop to endStop
        $tripReservations = $this->reservationRepository->findByWhere(
            ['planned_trip_id' => $plannedTrip->id], ['*'], ['plannedTrip']);
        $reservationCount = 0;

        $route_id = $route->id;
        $start_stop_id = $startStop->id;
        $end_stop_id = $endStop->id;
        $startRouteStop = $this->routeStopRepository->findByWhere(['route_id' => $route_id, 'stop_id' => $start_stop_id], ['id', 'order'])->first();
        $endRouteStop = $this->routeStopRepository->findByWhere(['route_id' => $route_id, 'stop_id' => $end_stop_id], ['id', 'order'])->first();

        //get all stops from startStop to endStop on the route
        $routeStops = $this->routeStopRepository->findByWhere([
            ['route_id', '=', $route->id],
            ['order', '>=', $startRouteStop->order],
            ['order', '<=', $endRouteStop->order]
        ], ['*'], ['stop']);
        $routeStopsIds = $routeStops->pluck('id')->toArray();

        foreach ($tripReservations as $tripReservation) {
            $reservationStopIDs = $this->getReservationStopIDs($tripReservation);
            // Log::info("reservationStopIDs");
            // Log::info($reservationStopIDs);

            // check if the routeStops intersect with the reservationStopIDs
            $intersection = array_intersect($reservationStopIDs, $routeStopsIds);
            if (count($intersection) > 0) {
                $reservationCount ++;
            }
            // //check if $startStop is in the reservationStopIDs
            // if (in_array($startStop->id, $reservationStopIDs) ||
            //     in_array($endStop->id, $reservationStopIDs)) {
            //     $reservationCount ++;
            // }
        }
        Log::info("The trip has ". $reservationCount. " reservations");
        // get the capacity of the bus
        $capacity = $plannedTrip->bus->capacity;

        $availableSeats = $capacity - $reservationCount;

        return $availableSeats;
    }

    private function getReservationStopIDs($tripReservation)
    {
        $reservationStopIDs = [];
        $startStopID = $tripReservation->start_stop_id;
        $endStopID = $tripReservation->end_stop_id;
        array_push($reservationStopIDs, $startStopID);
        $routeID = $tripReservation->plannedTrip->route_id;
        // Log::info("startStopID");
        // Log::info($startStopID);
        // Log::info("routeID");
        // Log::info($routeID);
        $startRouteStop = $this->routeStopRepository->findByWhere(['route_id' => $routeID, 'stop_id' => $startStopID], ['id', 'order'])->first();
        $endRouteStop = $this->routeStopRepository->findByWhere(['route_id' => $routeID, 'stop_id' => $endStopID], ['id', 'order'])->first();
        //Get route_stop ids for the route_stop in the route whose ID is route_id and order is greater than the order of the route_stop whose ID is route_stop_id
        $routeStopIds = $this->routeStopRepository->findByWhere([
            ['route_id', $routeID],
            ['order', '>', $startRouteStop->order],
            ['order', '<=', $endRouteStop->order],
            //['stop_id', '!=', $endStopID]
        ], ['id'])->pluck('id')->toArray();
        // Log::info("routeStopIds");
        // Log::info($routeStopIds);
        //get stops for the route_stop_ids
        $stops = $this->routeStopRepository->findByWhereIn('id', $routeStopIds, ['stop_id'])->pluck('stop_id')->toArray();
        //add to reservationStopIDs
        $reservationStopIDs = array_merge($reservationStopIDs, $stops);
        return $reservationStopIDs;
    }

    private function getClosestStopOnRoute($routeId, $closeStartStop, $point)
    {
        $routeStops = $this->routeStopRepository->findByWhere([['route_id', '=', $routeId], ['stop_id', '!=', $closeStartStop->id]], ['*'], ['stop']);
        $closestStop = null;
        $closestDistance = INF;
        foreach ($routeStops as $routeStop) {
            $distance = $this->distance($point['lat'], $point['lng'], $routeStop->stop->lat, $routeStop->stop->lng);
            if ($distance < $closestDistance) {
                $closestStop = $routeStop->stop;
                $closestDistance = $distance;
            }
        }
        $returnData= new stdClass();
        $returnData->stop = $closestStop;
        $returnData->distance = $closestDistance;
        return $returnData;
    }

    //Calculate price for a trip
    public function calcPrice($path)
    {
        if($path == null){
            return 0;
        }
        $retPriceData= new stdClass();
        $distance = 0;
        foreach ($path as $index => $point) {
            if ($index > 0) {
                $distance += $this->distance($path[$index - 1]->lat, $path[$index - 1]->lng, $point->lat, $point->lng);
            }
        }
        $currentSettings = Setting::where("id", 1)->first();
        $ratePerKm = $currentSettings->rate_per_km;
        $commission = $currentSettings->commission;
        $price = $distance * $ratePerKm * (1 + $commission/100.0);
        $retPriceData->distance = $distance;
        $retPriceData->price = $price;
        return $retPriceData;
    }



    public function pay(Request $request)
    {
        //validate the request
        $validator = $this->validate($request, [
            'trip_search_result_id' => 'required|integer',
            'payment_method' => 'required|integer',
        ], [] , []);

        $trip_search_result_id = $request->trip_search_result_id;

        $trip_search_result = $this->tripSearchResultRepository->findById($trip_search_result_id);

        $planned_trip_id = $trip_search_result->planned_trip_id;

        $trip = $this->plannedTripRepository->findById($planned_trip_id);
        if ($trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        $trip_search_result_user_id = $trip_search_result->user_id;
        if ($trip_search_result_user_id != $user_id) {
            return response()->json(["message" => "Unauthorized", "success" => false], 401);
        }

        $currentSettings = Setting::where("id", 1)->first();
        $commission = $currentSettings->commission;

        $payment_method = $request->payment_method;
        if($payment_method == 0) //wallet
        {
            $user_balance = $user->wallet;
            $systemPaymentMethod = $this->getPaymentMethod();
            if($systemPaymentMethod != "none"){
                $price = $trip_search_result->price;
            }
            else{
                $price = 0.0;
            }


            if ($user_balance < $price) {
                return response()->json(["message" => "Insufficient funds", "success" => false], 400);
            }

            DB::beginTransaction();
            try {
                $user->wallet = $user_balance - $price;
                $user->save();

                //driver share
                $driver_share = $price * (1 - $commission/100.0);
                //admin share
                $admin_share = $price * $commission/100.0;

                //Reservation
                $reservation = $this->reservationRepository->create([
                    "user_id" => $user_id,
                    "ticket_number" => uniqid(),
                    "planned_trip_id" => $planned_trip_id,
                    "paid_price" => $price,
                    "trip_price" => $price,
                    "admin_share" => $admin_share,
                    "driver_share" => $driver_share,
                    "payment_method" => $payment_method,
                    "reservation_date" => Carbon::now(),
                    "start_stop_id" => $trip_search_result->start_stop_id,
                    "end_stop_id" => $trip_search_result->end_stop_id,
                    "end_point_lat" => $trip_search_result->end_point_lat,
                    "end_point_lng" => $trip_search_result->end_point_lng,
                    "start_address" => $trip_search_result->start_address,
                    "destination_address" => $trip_search_result->destination_address,
                    "planned_start_time" => $trip_search_result->planned_start_time,
                    "status_id" => 1,
                ]);

                DB::commit();
                return response()->json(["message" => "Payment successful", "success" => true, "new_wallet_balance" => $user->wallet], 200);
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
    }

    //getTripDetails
    public function getTripDetails($trip_id)
    {
        $trip = $this->tripRepository->findById($trip_id, ['*'], ['tripDetails.stop']);
        if ($trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        Log::info("trip");
        Log::info($trip);

        $trip->trip_detail = $trip->tripDetails;

        return response()->json(["success" => true, "trip" => $trip], 200);
    }

    //getPlannedTripDetails
    public function getPlannedTripDetails($planned_trip_id)
    {
        Log::info("getPlannedTripDetails");

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        Log::info("planned_trip");
        Log::info($planned_trip);

        $user = Auth::user();
        $user_id = $user->id;

        $planned_trip_details = $planned_trip->plannedTripDetail;

        // $planned_trip_details = $planned_trip_details->map(function ($planned_trip_detail) {
        //     $planned_trip_detail->stop = $planned_trip_detail->stop;
        //     return $planned_trip_detail;
        // });

        // $planned_trip->plannedTripDetail = $planned_trip_details;

        return response()->json(["success" => true, "trip" => $planned_trip], 200);
    }


    // //startStopDriverTrip
    // public function startStopDriverTrip(Request $request)
    // {
    //     Log::info("startStopDriverTrip");

    //     //validate the request
    //     $this->validate($request, [
    //         'is_morning' => 'required|integer', // 1: morning, 0: afternoon
    //         'mode' => 'required|integer', // 1: start, 0: end
    //         'planned_trip_id' => 'integer|nullable',
    //     ], [], []);

    //     $is_morning = $request->is_morning;
    //     $planed_trip_id = 4246;
    //     return $this->startStopPlannedTrip($is_morning, $planed_trip_id);
    // }

    // public function startStopDriverTripWithGoogle(Request $request)
    // {
    //     Log::info("startStopDriverTrip");

    //     //validate the request
    //     $this->validate($request, [
    //         'is_morning' => 'required|integer', // 1: morning, 0: afternoon
    //         'mode' => 'required|integer', // 1: start, 0: end
    //         'planned_trip_id' => 'integer|nullable',
    //     ], [], []);

    //     $is_morning = $request->is_morning;

    //     $user = Auth::user();
    //     $driver_id = $user->id;
    //     $driver_id = 71;

    //     //get the driver with bus
    //     $driver = $this->userRepository->findById($driver_id, ['*'], ['bus', 'driverSchool.schoolSettings']);

    //     if ($driver == null) {
    //         return response()->json(["message" => "Driver not found", "success" => false], 404);
    //     }

    //     if ($driver->bus == null) {
    //         return response()->json(["message" => "Driver does not have a bus", "success" => false], 404);
    //     }

    //     $mode = $request->mode; // 1: start, 0: end
    //     if ($mode == 1) {
    //         // get all pickups for morning and drop-off for afternoon for all students in the school of the driver
    //         $school_id = $driver->school_id;
    //         $school = $this->userRepository->findById($school_id, ['*'], ['schoolStudents.studentSettings', 'schoolSettings']);
    //         $students = $school->schoolStudents;
    //         //filter students with settings matching the driver's bus
    //         $students = $students->filter(function ($student) use ($driver, $is_morning) {
    //             if ($is_morning == 1) {
    //                 return $student->studentSettings->morning_bus_id == $driver->bus->id;
    //             }
    //             else
    //             {
    //                 return $student->studentSettings->afternoon_bus_id == $driver->bus->id;
    //             }
    //         });
    //         $stops = $students->map(function ($student) use ($is_morning) {
    //             if ($is_morning == 1) {
    //                 return [
    //                     'lat' => $student->studentSettings->pickup_lat,
    //                     'lng' => $student->studentSettings->pickup_lng,
    //                     'address' => $student->studentSettings->pickup_address,
    //                     'place_id' => $student->studentSettings->pickup_place_id,
    //                 ];
    //             } else {
    //                 return [
    //                     'lat' => $student->studentSettings->drop_off_lat,
    //                     'lng' => $student->studentSettings->drop_off_lng,
    //                     'address' => $student->studentSettings->drop_off_address,
    //                     'place_id' => $student->studentSettings->drop_off_place_id,
    //                 ];
    //             }
    //         });

    //         //add student_index'] to stops
    //         $stops = $stops->map(function ($stop, $index) {
    //             $stop['student_index'] = $index;
    //             return $stop;
    //         });
    //         $school_location = [
    //             'lat' => $school->schoolSettings->lat,
    //             'lng' => $school->schoolSettings->lng,
    //             'address' => $school->schoolSettings->address,
    //             'place_id' => $school->schoolSettings->place_id,
    //         ];
    //         //order stops based on the closest to the school location
    //         $stops = $stops->sortBy(function ($stop) use ($school_location) {
    //             $distance = $this->distance($stop['lat'], $stop['lng'], $school_location['lat'], $school_location['lng']);
    //             return $distance;
    //         });
    //         $stops = $stops->values();
    //         if($is_morning == 1)
    //         {
    //             //reverse the stops
    //             $stops = $stops->reverse();
    //         }
    //         //add school location to the stops at the end
    //         $stops->push($school_location);

    //         Log::info("stops");
    //         Log::info($stops);
    //         $start_lat = $stops[0]['lat'];
    //         $start_lng = $stops[0]['lng'];
    //         $end_lat = $stops[count($stops) - 1]['lat'];
    //         $end_lng = $stops[count($stops) - 1]['lng'];
    //         $waypoints = $stops->slice(1, count($stops) - 2)->toArray();
    //         //convert to string
    //         $waypoints = array_map(function ($waypoint) {
    //             return $waypoint['lat'] . ',' . $waypoint['lng'] . '|';
    //         }, $waypoints);
    //         $waypoints = implode($waypoints);
    //         $waypoints = rtrim($waypoints, '|');
    //         Log::info("waypoints");
    //         Log::info($waypoints);
    //         Log::info("start_lat");
    //         Log::info([$start_lat . ',' . $start_lng]);
    //         Log::info("end_lat");
    //         Log::info([$end_lat . ',' . $end_lng]);
    //         // $response = \GoogleMaps::load('directions')
    //         // ->setParam ([
    //         //     'origin' => [$start_lat . ',' . $start_lng],
    //         //     'destination' => [$end_lat . ',' . $end_lng],
    //         //     'waypoints' => $waypoints,
    //         //     'mode' => 'driving',
    //         //     'optimize' => true,
    //         // ])
    //         // ->get();
    //         // Log::info("response");
    //         // Log::info($response);
    //         // //save response to file
    //         $file = 'response.json';
    //         // file_put_contents(public_path($file), $response);
    //         //read response from file
    //         $response = file_get_contents(public_path($file));
    //         // $response = json_decode($response, true);
    //         // Log::info("response");
    //         // Log::info($response);

    //         $response = json_decode($response, true);
    //         //get stops from the response
    //         $route = $response['routes'][0];
    //         $geocoded_waypoints = $response['geocoded_waypoints'];
    //         $overview_path = $route['overview_polyline']['points'];
    //         $route_name = $route['summary'];
    //         $waypoint_order = $route['waypoint_order'];

    //         //add place id from geocoded_waypoints to stops
    //         $stops = $stops->map(function ($stop, $index) use ($geocoded_waypoints) {
    //             $stop['place_id'] = $geocoded_waypoints[$index]['place_id'];
    //             $stop['name'] = count($geocoded_waypoints[$index]['types'])>0 ? $geocoded_waypoints[$index]['types'][0]. '-' . "stop ".($index+1) : "stop ".($index+1);
    //             return $stop;
    //         });
    //         $stops = $stops->values();

    //         //re-order stops based on waypoint_order
    //         $ordered_stops = [];
    //         array_push($ordered_stops, $stops[0]);
    //         foreach ($waypoint_order as $index) {
    //             array_push($ordered_stops, $stops[$index + 1]);
    //         }
    //         ////////////////////////ordered_stops////////////////////////
    //         array_push($ordered_stops, $stops[count($stops) - 1]);
    //         Log::info("ordered_stops");
    //         Log::info($ordered_stops);
    //         $legs = $route['legs'];
    //         //time of each leg
    //         $leg_times = [0];
    //         ////////////////////////leg_times////////////////////////
    //         foreach ($legs as $leg) {
    //             $timeInMinutes = $leg['duration']['value'] / 60;
    //             //round to the nearest 1 minute
    //             $timeInMinutes = round($timeInMinutes);
    //             array_push($leg_times, $timeInMinutes);
    //         }
    //         Log::info("leg_times");
    //         Log::info($leg_times);
    //         //get overview_polyline for each leg
    //         ////////////////////////ordered_directions////////////////////////
    //         $ordered_directions = [];
    //         $lastIndex = 0;
    //         foreach ($legs as $leg) {
    //             // get end_location of each leg
    //             $end_location = $leg['end_location'];
    //             //get the closest point on overview_polyline to the end_location
    //             $closestPointIndex = $this->getClosestPointOnOverviewPolyline($overview_path, $end_location, $lastIndex);
    //             $leg_overview_polyline = array_slice($overview_path, $lastIndex, $closestPointIndex - $lastIndex);
    //             array_push($ordered_directions, $leg_overview_polyline);
    //             $lastIndex = $closestPointIndex;
    //         }

    //         DB::beginTransaction();
    //         try {
    //             $routeDetails = [
    //                 'name' => $route_name,
    //                 'is_morning' => $is_morning, //1 - morning, 0 - afternoon
    //                 'school_id' => $school_id,
    //             ];

    //             //create new route
    //             $savedRoute = $this->routeRepository->create($routeDetails);


    //             $allStops = [];
    //             $order = 1;
    //             for ($i=0; $i < count($ordered_stops); $i++) {
    //                 $stop = $ordered_stops[$i];
    //                 $newStop = [
    //                     'name' => $stop['name'],
    //                     'lat' => $stop['lat'],
    //                     'lng' => $stop['lng'],
    //                     'address' => $stop['address'],
    //                     'place_id' => $stop['place_id'],
    //                     'school_id' => $school_id,
    //                 ];
    //                 //create the stop
    //                 $savedStop = $this->stopRepository->create($newStop);
    //                 array_push($allStops, $savedStop);
    //                 $savedStopId = $savedStop->id;
    //                 $routeStop = [
    //                     'stop_id' => $savedStopId,
    //                     'route_id' => $savedRoute->id,
    //                     'order' => $order,
    //                 ];
    //                 $savedRouteStop = $this->routeStopRepository->create($routeStop);
    //                 if($i > 0)
    //                 {
    //                     //loop through ordered directions and save them to the database
    //                     $direction = $ordered_directions[$i-1];
    //                     $directionDetails = [
    //                         'route_stop_id' => $savedRouteStop->id,
    //                         'summary' => "Leg " . $i,
    //                         'index' => $i-1,
    //                         'overview_path' => json_encode($direction),
    //                         'current' => 1,
    //                     ];
    //                     $this->routeStopDirectionRepository->create($directionDetails);
    //                 }
    //                 $order = $order + 1;
    //             }
    //             //create a trip on the route
    //             $todayDate = Carbon::now()->toDateString();
    //             $nowTime = Carbon::now()->toTimeString();
    //             $tripDetails = [
    //                 'channel' => uniqid(),
    //                 'driver_id' => $driver_id,
    //                 'bus_id' => $driver->bus_id,
    //                 'route_id' => $savedRoute->id,
    //                 'stop_to_stop_avg_time' => 0,
    //                 'effective_date' => $todayDate,
    //                 'repetition_period' => 1,
    //                 'first_stop_time' => $nowTime,
    //                 'school_id' => $school_id,
    //                 'status_id' => 1,
    //             ];
    //             $savedTrip = $this->tripRepository->create($tripDetails);
    //             $tripID = $savedTrip->id;

    //             $lastStopTime = 0;
    //             for ($i = 0; $i < count($allStops); $i++) {
    //                 $stop_id = $allStops[$i]->id;
    //                 $t = $leg_times[$i];
    //                 if ($i == 0)
    //                     $lastStopTime = $savedTrip['first_stop_time'];
    //                 $lastStopTime = strtotime('+' . $t . 'minutes', strtotime($lastStopTime));
    //                 $lastStopTime = date('H:i:s', $lastStopTime);
    //                 $tripDetail = [
    //                     'stop_id' => $stop_id,
    //                     'planned_timestamp' => $lastStopTime,
    //                     'inter_time' => $t,
    //                     'trip_id' => $tripID
    //                 ];
    //                 $this->tripDetailRepository->create($tripDetail);
    //             }

    //             //update last_stop_time
    //             $trip = $this->tripRepository->findById($tripID, ['*'], ['tripDetails']);
    //             $trip->last_stop_time = $lastStopTime;
    //             $trip->save();

    //             //create a planned trip for the trip
    //             $this->publishTrips();
    //             //check if there is a planned trip for the trip
    //             $planned_trip = $this->plannedTripRepository->findByWhere([['trip_id', '=', $tripID]], ['*'])->first();
    //             if($planned_trip == null)
    //             {
    //                 return response()->json(["message" => "Planned trip not found", "success" => false], 404);
    //             }

    //             //update students settings
    //             for($i = 0; $i < count($ordered_stops); $i++)
    //             {
    //                 //check if ordered_stops[$i] contains student_index
    //                 if(array_key_exists('student_index', $ordered_stops[$i]) == false)
    //                     continue;
    //                 $student_index = $ordered_stops[$i]['student_index'];
    //                 $student = $students[$student_index];
    //                 $stop_id = $allStops[$i]->id;
    //                 Log::info("assigned stop " . $allStops[$i] . " to student " . $student->id);
    //                 $studentSettings = $student->studentSettings;
    //                 if($is_morning)
    //                 {
    //                     $studentSettings->pickup_trip_id = $tripID;
    //                     $pickup_route_stop = $this->routeStopRepository->findByWhere([['route_id', '=', $savedRoute->id], ['stop_id', '=', $stop_id]], ['*'])->first();
    //                     $studentSettings->pickup_route_stop_id = $pickup_route_stop->id;
    //                 }
    //                 else
    //                 {
    //                     $studentSettings->drop_off_trip_id = $tripID;
    //                     $drop_off_route_stop = $this->routeStopRepository->findByWhere([['route_id', '=', $savedRoute->id], ['stop_id', '=', $stop_id]], ['*'])->first();
    //                     $studentSettings->drop_off_route_stop_id = $drop_off_route_stop->id;
    //                 }

    //                 $studentSettings->save();
    //             }
    //             $this->assignStudentsToTrips();

    //             //save
    //             DB::commit();
    //         } catch (\Exception $e) {
    //             DB::rollback();
    //             return response()->json(['message' => $e->getMessage() . ', '. $e->getFile() . ', '. $e->getLine()], 422);
    //         }
    //     } else {
    //         $planned_trip_id = $request->planned_trip_id;
    //         if($planned_trip_id == null)
    //         {
    //             return response()->json(["message" => "Planned trip id is required", "success" => false], 400);
    //         }
    //         // check if all passengers are dropped off
    //         $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);
    //         $passengersToBeDroppedOff = $allPassengers->filter(function ($passenger) {
    //             return $passenger->ride_status == 1;
    //         });
    //         if (count($passengersToBeDroppedOff) > 0) {
    //             return response()->json(["message" => "There are passengers to be dropped off", "success" => false], 400);
    //         }
    //         $planned_trip->ended_at = Carbon::now();
    //     }

    //     //return response()->json(["success" => true, "trip" => $planned_trip], 200);
    // }
    // public function startStopDriverTripOld(Request $request)
    // {
    //     Log::info("startStopDriverTrip");

    //     //validate the request
    //     $this->validate($request, [
    //         //lat, lng
    //         'lat' => 'required|numeric',
    //         'lng' => 'required|numeric',
    //         'is_morning' => 'required|integer', // 1: morning, 0: afternoon
    //         'mode' => 'required|integer', // 1: start, 0: end
    //         'planned_trip_id' => 'integer|nullable',
    //     ], [], []);

    //     $start_lat = $request->lat;
    //     $start_lng = $request->lng;
    //     $is_morning = $request->is_morning;

    //     $user = Auth::user();
    //     $driver_id = $user->id;
    //     $driver_id = 71;

    //     //get the driver with bus
    //     $driver = $this->userRepository->findById($driver_id, ['*'], ['bus', 'driverSchool.schoolSettings']);

    //     if ($driver == null) {
    //         return response()->json(["message" => "Driver not found", "success" => false], 404);
    //     }

    //     if ($driver->bus == null) {
    //         return response()->json(["message" => "Driver does not have a bus", "success" => false], 404);
    //     }

    //     $mode = $request->mode; // 1: start, 0: end
    //     if ($mode == 1) {
    //         // get all pickups for morning and drop-off for afternoon for all students in the school of the driver
    //         $school_id = $driver->school_id;
    //         $school = $this->userRepository->findById($school_id, ['*'], ['schoolStudents.studentSettings', 'schoolSettings']);
    //         $students = $school->schoolStudents;
    //         //filter students with settings matching the driver's bus
    //         $students = $students->filter(function ($student) use ($driver, $is_morning) {
    //             if ($is_morning == 1) {
    //                 return $student->studentSettings->morning_bus_id == $driver->bus->id;
    //             }
    //             else
    //             {
    //                 return $student->studentSettings->afternoon_bus_id == $driver->bus->id;
    //             }
    //         });
    //         $stops = $students->map(function ($student) use ($is_morning) {
    //             if ($is_morning == 1) {
    //                 return [
    //                     'lat' => $student->studentSettings->pickup_lat,
    //                     'lng' => $student->studentSettings->pickup_lng,
    //                     'address' => $student->studentSettings->pickup_address,
    //                     'place_id' => $student->studentSettings->pickup_place_id,
    //                 ];
    //             } else {
    //                 return [
    //                     'lat' => $student->studentSettings->drop_off_lat,
    //                     'lng' => $student->studentSettings->drop_off_lng,
    //                     'address' => $student->studentSettings->drop_off_address,
    //                     'place_id' => $student->studentSettings->drop_off_place_id,
    //                 ];
    //             }
    //         });
    //         $school_location = [
    //             'lat' => $school->schoolSettings->lat,
    //             'lng' => $school->schoolSettings->lng,
    //             'address' => $school->schoolSettings->address,
    //             'place_id' => $school->schoolSettings->place_id,
    //         ];
    //         // $start_stop = [
    //         //     'lat' => $start_lat,
    //         //     'lng' => $start_lng,
    //         //     'address' => '',
    //         //     'place_id' => '',
    //         // ];
    //         // $stops->prepend($start_stop);
    //         if($is_morning == 1)
    //         {
    //             //add school location to the stops at the end
    //             $stops->push($school_location);
    //         }
    //         else
    //         {
    //             //add school location to the stops at the beginning
    //             $stops->prepend($school_location);
    //             $start_lat = $school_location['lat'];
    //             $start_lng = $school_location['lng'];
    //         }
    //         Log::info("stops");
    //         Log::info($stops);
    //         $end_lat = $stops[count($stops) - 1]['lat'];
    //         $end_lng = $stops[count($stops) - 1]['lng'];
    //         $waypoints = $stops->slice(0, count($stops) - 1)->toArray();
    //         //convert to string
    //         $waypoints = array_map(function ($waypoint) {
    //             return $waypoint['lat'] . ',' . $waypoint['lng'] . '|';
    //         }, $waypoints);
    //         $waypoints = implode($waypoints);
    //         $waypoints = rtrim($waypoints, '|');
    //         Log::info("waypoints");
    //         Log::info($waypoints);
    //         // $response = \GoogleMaps::load('directions')
    //         // ->setParam ([
    //         //     'origin' => [$start_lat . ',' . $start_lng],
    //         //     'destination' => [$end_lat . ',' . $end_lng],
    //         //     'waypoints' => $waypoints,
    //         //     'mode' => 'driving',
    //         //     'optimize' => true,
    //         // ])
    //         // ->get();
    //         // Log::info("response");
    //         // Log::info($response);
    //         // //save response to file
    //         $file = 'response.json';
    //         // file_put_contents(public_path($file), $response);
    //         //read response from file
    //         $response = file_get_contents(public_path($file));
    //         // $response = json_decode($response, true);
    //         // Log::info("response");
    //         // Log::info($response);

    //         $response = json_decode($response, true);
    //         //get stops from the response
    //         $route = $response['routes'][0];
    //         $geocoded_waypoints = $response['geocoded_waypoints'];
    //         $overview_path = $route['overview_polyline']['points'];
    //         $route_name = $route['summary'];
    //         $waypoint_order = $route['waypoint_order'];


    //         //add place id from geocoded_waypoints to stops
    //         $stops = $stops->map(function ($stop, $index) use ($geocoded_waypoints) {
    //             $stop['place_id'] = $geocoded_waypoints[$index+1]['place_id'];
    //             $stop['name'] = count($geocoded_waypoints[$index+1]['types'])>0 ? $geocoded_waypoints[$index+1]['types'][0]. '-' . "stop ".($index+1) : "stop ".($index+1);
    //             $stop['student_index'] = $index;
    //             return $stop;
    //         });

    //         //re-order stops based on waypoint_order
    //         $ordered_stops = [];
    //         array_push($ordered_stops, $stops[0]);
    //         foreach ($waypoint_order as $index) {
    //             array_push($ordered_stops, $stops[$index + 1]);
    //         }
    //         ////////////////////////ordered_stops////////////////////////
    //         array_push($ordered_stops, $stops[count($stops) - 1]);

    //         $legs = $route['legs'];
    //         //time of each leg
    //         $leg_times = [0];
    //         ////////////////////////leg_times////////////////////////
    //         foreach ($legs as $leg) {
    //             $timeInMinutes = $leg['duration']['value'] / 60;
    //             //round to the nearest 1 minute
    //             $timeInMinutes = round($timeInMinutes);
    //             array_push($leg_times, $timeInMinutes);
    //         }
    //         Log::info("leg_times");
    //         Log::info($leg_times);
    //         //get overview_polyline for each leg
    //         ////////////////////////ordered_directions////////////////////////
    //         $ordered_directions = [];
    //         $lastIndex = 0;
    //         foreach ($legs as $leg) {
    //             // get end_location of each leg
    //             $end_location = $leg['end_location'];
    //             //get the closest point on overview_polyline to the end_location
    //             $closestPointIndex = $this->getClosestPointOnOverviewPolyline($overview_path, $end_location, $lastIndex);
    //             $leg_overview_polyline = array_slice($overview_path, $lastIndex, $closestPointIndex - $lastIndex);
    //             array_push($ordered_directions, $leg_overview_polyline);
    //             $lastIndex = $closestPointIndex;
    //         }

    //         DB::beginTransaction();
    //         try {
    //             $routeDetails = [
    //                 'name' => $route_name,
    //                 'is_morning' => $is_morning, //1 - morning, 0 - afternoon
    //                 'school_id' => $school_id,
    //             ];

    //             //create new route
    //             $savedRoute = $this->routeRepository->create($routeDetails);


    //             $allStops = [];
    //             $order = 1;
    //             for ($i=0; $i < count($ordered_stops); $i++) {
    //                 $stop = $ordered_stops[$i];
    //                 $newStop = [
    //                     'name' => $stop['name'],
    //                     'lat' => $stop['lat'],
    //                     'lng' => $stop['lng'],
    //                     'address' => $stop['address'],
    //                     'place_id' => $stop['place_id'],
    //                     'school_id' => $school_id,
    //                 ];
    //                 //create the stop
    //                 $savedStop = $this->stopRepository->create($newStop);
    //                 array_push($allStops, $savedStop);
    //                 $savedStopId = $savedStop->id;
    //                 $routeStop = [
    //                     'stop_id' => $savedStopId,
    //                     'route_id' => $savedRoute->id,
    //                     'order' => $order,
    //                 ];
    //                 $savedRouteStop = $this->routeStopRepository->create($routeStop);
    //                 if($i > 0)
    //                 {
    //                     //loop through ordered directions and save them to the database
    //                     $direction = $ordered_directions[$i-1];
    //                     $directionDetails = [
    //                         'route_stop_id' => $savedRouteStop->id,
    //                         'summary' => "Leg " . $i,
    //                         'index' => $i-1,
    //                         'overview_path' => json_encode($direction),
    //                         'current' => 1,
    //                     ];
    //                     $this->routeStopDirectionRepository->create($directionDetails);
    //                 }
    //                 $order = $order + 1;
    //             }
    //             //create a trip on the route
    //             $todayDate = Carbon::now()->toDateString();
    //             $nowTime = Carbon::now()->toTimeString();
    //             $tripDetails = [
    //                 'channel' => uniqid(),
    //                 'driver_id' => $driver_id,
    //                 'bus_id' => $driver->bus_id,
    //                 'route_id' => $savedRoute->id,
    //                 'stop_to_stop_avg_time' => 0,
    //                 'effective_date' => $todayDate,
    //                 'repetition_period' => 1,
    //                 'first_stop_time' => $nowTime,
    //                 'school_id' => $school_id,
    //                 'status_id' => 1,
    //             ];
    //             $savedTrip = $this->tripRepository->create($tripDetails);
    //             $tripID = $savedTrip->id;

    //             $lastStopTime = 0;
    //             for ($i = 0; $i < count($allStops); $i++) {
    //                 $stop_id = $allStops[$i]->id;
    //                 $t = $leg_times[$i];
    //                 if ($i == 0)
    //                     $lastStopTime = $savedTrip['first_stop_time'];
    //                 $lastStopTime = strtotime('+' . $t . 'minutes', strtotime($lastStopTime));
    //                 $lastStopTime = date('H:i:s', $lastStopTime);
    //                 $tripDetail = [
    //                     'stop_id' => $stop_id,
    //                     'planned_timestamp' => $lastStopTime,
    //                     'inter_time' => $t,
    //                     'trip_id' => $tripID
    //                 ];
    //                 $this->tripDetailRepository->create($tripDetail);
    //             }

    //             //update last_stop_time
    //             $trip = $this->tripRepository->findById($tripID, ['*'], ['tripDetails']);
    //             $trip->last_stop_time = $lastStopTime;
    //             $trip->save();

    //             //create a planned trip for the trip
    //             $this->publishTrips();
    //             //check if there is a planned trip for the trip
    //             $planned_trip = $this->plannedTripRepository->findByWhere([['trip_id', '=', $tripID]], ['*'])->first();
    //             if($planned_trip == null)
    //             {
    //                 return response()->json(["message" => "Planned trip not found", "success" => false], 404);
    //             }

    //             //update students settings
    //             for($i = 0; $i < count($students); $i++)
    //             {
    //                 $student = $students[$i];
    //                 $student_index = $ordered_stops[$i]['student_index'];
    //                 $stop_id = $allStops[$student_index]->id;
    //                 $studentSettings = $student->studentSettings;
    //                 if($is_morning)
    //                 {
    //                     $studentSettings->pickup_trip_id = $tripID;
    //                     $pickup_route_stop = $this->routeStopRepository->findByWhere([['route_id', '=', $savedRoute->id], ['stop_id', '=', $stop_id]], ['*'])->first();
    //                     $studentSettings->pickup_route_stop_id = $pickup_route_stop->id;
    //                 }
    //                 else
    //                 {
    //                     $studentSettings->drop_off_trip_id = $tripID;
    //                     $drop_off_route_stop = $this->routeStopRepository->findByWhere([['route_id', '=', $savedRoute->id], ['stop_id', '=', $stop_id]], ['*'])->first();
    //                     $studentSettings->drop_off_route_stop_id = $drop_off_route_stop->id;
    //                 }

    //                 $studentSettings->save();
    //             }

    //             $this->assignStudentsToTrips();

    //             //save
    //             //DB::commit();
    //         } catch (\Exception $e) {
    //             DB::rollback();
    //             return response()->json(['message' => $e->getMessage() . ', '. $e->getFile() . ', '. $e->getLine()], 422);
    //         }
    //     } else {
    //         $planned_trip_id = $request->planned_trip_id;
    //         if($planned_trip_id == null)
    //         {
    //             return response()->json(["message" => "Planned trip id is required", "success" => false], 400);
    //         }
    //         // check if all passengers are dropped off
    //         $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);
    //         $passengersToBeDroppedOff = $allPassengers->filter(function ($passenger) {
    //             return $passenger->ride_status == 1;
    //         });
    //         if (count($passengersToBeDroppedOff) > 0) {
    //             return response()->json(["message" => "There are passengers to be dropped off", "success" => false], 400);
    //         }
    //         $planned_trip->ended_at = Carbon::now();
    //     }

    //     //return response()->json(["success" => true, "trip" => $planned_trip], 200);
    // }

    private function getClosestPointOnOverviewPolyline($overview_path, $end_location, $startIndex = 0)
    {
        $closestDistance = INF;
        $closestPointIndex = -1;
        //loop through the overview_path and get the closest point to the end_location
        for($i = $startIndex; $i < count($overview_path); $i++)
        {
            $point = $overview_path[$i];
            $distance = $this->distance($point['lat'], $point['lng'], $end_location['lat'], $end_location['lng']);
            if ($distance < $closestDistance) {
                $closestPoint = $point;
                $closestDistance = $distance;
                $closestPointIndex = $i;
            }
        }
        return $closestPointIndex;
    }

    //startStopPlannedTrip
    public function startStopPlannedTrip(Request $request)
    {
        Log::info("startStopPlannedTrip");

        //validate the request
        $this->validate($request, [
            'planned_trip_id' => 'required|integer',
            'mode' => 'required|integer',
            'trip_details' => 'array',
        ], [], []);

        $planned_trip_id = $request->planned_trip_id;

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        //check if the user is the driver of the trip
        // if ($planned_trip->driver_id != $user_id) {
        //     return response()->json(["message" => "Unauthorized", "success" => false], 401);
        // }

        $mode = $request->mode; // 1: start, 0: end
        if ($mode == 1) {
            $setting = $this->settingRepository->all()->first();
            if($setting->simple_mode == 1)
            {
                $orderedPlannedTripDetails = $request->trip_details;
                //sort the $planned_trip->plannedTripDetails based on the order in $orderedPlannedTripDetails
                $newPlannedTripDetail = $planned_trip->plannedTripDetail->sortBy(function ($plannedTripDetail) use ($orderedPlannedTripDetails) {
                    $stop_id = $plannedTripDetail->stop_id;
                    $index = -1;
                    foreach ($orderedPlannedTripDetails as $key => $orderedPlannedTripDetail) {
                        if ($orderedPlannedTripDetail['stop_id'] == $stop_id) {
                            $index = $key;
                            break;
                        }
                    }
                    return $index;
                });
                // Log::info("planned_trip->plannedTripDetails after sorting");
                // Log::info($newPlannedTripDetail);
                DB::beginTransaction();
                try {
                    //delete all planned trip details for the planned trip
                    PlannedTripDetail::where('planned_trip_id', $planned_trip_id)->delete();
                    //create new planned trip details
                    foreach ($newPlannedTripDetail as $plannedTripDetail) {
                        $newPlannedTripDetail = [
                            'planned_trip_id' => $planned_trip_id,
                            'stop_id' => $plannedTripDetail->stop_id,
                            'planned_timestamp' => $plannedTripDetail->planned_timestamp,
                        ];
                        PlannedTripDetail::create($newPlannedTripDetail);
                    }

                    $planned_trip->started_at = Carbon::now();
                    $planned_trip->save();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();
                    return response()->json(['message' => $e->getMessage() . ', '. $e->getFile() . ', '. $e->getLine()], 422);
                }
            }
            else
            {
                $planned_trip->started_at = Carbon::now();
                $planned_trip->save();
            }
        } else {
            // check if all passengers are dropped off
            $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);
            $passengersToBeDroppedOff = $allPassengers->filter(function ($passenger) {
                return $passenger->ride_status == 1;
            });
            if (count($passengersToBeDroppedOff) > 0) {
                return response()->json(["message" => "There are passengers to be dropped off", "success" => false], 400);
            }
            DB::beginTransaction();
            try {
                $planned_trip->ended_at = Carbon::now();
                $planned_trip->save();
                //check if simple mode
                $setting = $this->settingRepository->all()->first();
                if($setting->simple_mode == 1)
                {
                    //delete planned trip
                    // $this->plannedTripRepository->deleteById($planned_trip_id);
                    //delete the route
                    $trip = $this->tripRepository->findById($planned_trip->trip_id, ['*'], ['route.routeStops']);
                    //get the route
                    $route = $trip->route;
                    //get all stops on the route
                    $route_stops = $route->routeStops;
                    foreach ($route_stops as $route_stop) {
                        //delete stop
                        $this->stopRepository->deleteById($route_stop->stop_id);
                    }
                    // delete the route
                    $this->routeRepository->deleteById($route->id);
                }
                DB::commit();
                return response()->json(['success'=> true], 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json(["success" => true, "trip" => $planned_trip], 200);
    }

    public function sendStudentNotificationBasedOnSetting($notificationSetting, $student)
    {
        $student_id = $student->id;
        //get event type
        $eventType = $this->eventTypeRepository->findByWhere([['notification_name', '=', $notificationSetting]], ['*'])->first();
        $title = $eventType->title;

        //check if there is event for this student with the same event type recently
        $event = $this->eventRepository->findByWhere([['user_id', '=', $student_id], ['event_type_id', '=', $eventType->id], ['created_at', '>=', Carbon::now()->subMinutes(30)]], ['*'])->first();

        if($event == null)
        {
            //send notification to the student
            $this->sendNotificationToUser($student, $title);
            //save event
            $this->eventRepository->create([
                'user_id' => $student_id,
                'event_type_id' => $eventType->id,
            ]);
        }
    }

    //setLastPosition
    public function setLastPosition(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'planned_trip_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'speed' => 'required|numeric',
        ], [], []);

        $planned_trip_id = $request->planned_trip_id;

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        //check if the user is the driver of the trip
        if ($planned_trip->driver_id != $user_id) {
            return response()->json(["message" => "Unauthorized", "success" => false], 401);
        }

        $setting = $this->settingRepository->all()->first();
        $simple_mode = $setting->simple_mode;
        $distance_to_stop_to_mark_arrived = $setting->distance_to_stop_to_mark_arrived;

        //create a transaction
        DB::beginTransaction();
        try {
            $lat = $request->lat;
            $lng = $request->lng;
            $speed = $request->speed;

            // Log::info("lat = " . $lat . ", lng = " . $lng . ", speed = " . $speed);

            $planned_trip->last_position_lat = $lat;
            $planned_trip->last_position_lng = $lng;

            $planned_trip->save();

            $pos = array(
                'speed' => $speed,
                'lat' => $planned_trip->last_position_lat,
                'lng' => $planned_trip->last_position_lng);

            broadcast(new \App\Events\TripPositionUpdated($planned_trip->channel, json_encode($pos)));
            // Log::info("TripPositionUpdated on channel " . $planned_trip->channel. " with data " . json_encode($pos));

            //loop through the plannedTripDetails stops
            $planned_trip_details = $planned_trip->plannedTripDetail;
            // if($simple_mode == 0)
            if(true)
            {
                //get the next stop which do not have actual_timestamp
                $planned_trip_detail = $planned_trip_details->filter(function ($planned_trip_detail) {
                    return $planned_trip_detail->actual_timestamp == null;
                })->first();
                if ($planned_trip_detail != null) {
                    $next_stop = $planned_trip_detail->stop;
                    // Log::info("next_stop " . $next_stop);
                    $next_stop_planned_time = $planned_trip_detail->planned_timestamp;

                    $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]], ['*'], ['student.studentSettings']);

                    $distance = $this->distance($lat, $lng, $next_stop->lat, $next_stop->lng)*1000;

                    // filter $allPassengers based on ride_status
                    $passengersToBePickedUp = $allPassengers->filter(function ($passenger) use($next_stop) {
                        return $passenger->ride_status == 0 && $passenger->start_stop_id == $next_stop->id;
                    });

                    //loop through the passengersToBePickedUp
                    foreach ($passengersToBePickedUp as $passengerToBePickedUp) {
                        $student = $passengerToBePickedUp->student;
                        $studentSettings = $student->studentSettings;
                        //check notification settings

                        //next_stop_is_your_pickup_location_notification_on_off
                        if($studentSettings->next_stop_is_your_pickup_location_notification_on_off == 1)
                        {
                            $this->sendStudentNotificationBasedOnSetting("next_stop_is_your_pickup_location_notification_on_off", $student);
                        }
                        //bus_near_pickup_location_notification_by_distance
                        if($studentSettings->bus_near_pickup_location_notification_by_distance != null)
                        {
                            $distance_to_stop_to_notify = $studentSettings->bus_near_pickup_location_notification_by_distance;
                            if($distance < $distance_to_stop_to_notify)
                            {
                                $this->sendStudentNotificationBasedOnSetting("bus_near_pickup_location_notification_by_distance", $student);
                            }
                        }
                        //bus_arrived_at_pickup_location_notification_on_off
                        if($studentSettings->bus_arrived_at_pickup_location_notification_on_off == 1)
                        {
                            if($distance < $distance_to_stop_to_mark_arrived)
                            {
                                $this->sendStudentNotificationBasedOnSetting("bus_arrived_at_pickup_location_notification_on_off", $student);
                            }
                        }
                    }

                    $passengersToBeDroppedOff = $allPassengers->filter(function ($passenger) use($next_stop) {
                        //check ride_status == 1 and drop off point is near the current position
                        return ($passenger->ride_status == 1) &&
                        $passenger->end_stop_id == $next_stop->id;;
                    });

                    //loop through the passengersToBeDroppedOff
                    foreach ($passengersToBeDroppedOff as $passengerToBeDroppedOff) {
                        $student = $passengerToBeDroppedOff->student;
                        $studentSettings = $student->studentSettings;
                        //check notification settings
                        //bus_near_drop_off_location_notification_on_off
                        if($studentSettings->bus_near_drop_off_location_notification_on_off == 1)
                        {
                            $this->sendStudentNotificationBasedOnSetting("bus_near_drop_off_location_notification_on_off", $student);
                        }
                        //bus_arrived_at_drop_off_location_notification_on_off
                        if($studentSettings->bus_arrived_at_drop_off_location_notification_on_off == 1)
                        {
                            if($distance < $distance_to_stop_to_mark_arrived)
                            {
                                $this->sendStudentNotificationBasedOnSetting("bus_arrived_at_drop_off_location_notification_on_off", $student);
                            }
                        }
                    }
                    if ($distance < $distance_to_stop_to_mark_arrived && count($passengersToBePickedUp) == 0 && count($passengersToBeDroppedOff) == 0) {
                        $planned_trip_detail->actual_timestamp = Carbon::now();
                        $planned_trip_detail->save();
                    }
                    // Log::info("next_stop " . $next_stop->address . ", distanceToNextStop = " . $distanceToNextStop . ", passengersToBePickedUp = " . $passengersToBePickedUp . ", passengersToBeDroppedOff = " . $passengersToBeDroppedOff);

                    DB::commit();
                    return response()->json([
                        "success" => true,
                        "next_stop" => $next_stop,
                        "next_stop_planned_time" => $next_stop_planned_time,
                        "distance_to_next_stop" => $distance,
                        "count_passengers_to_be_picked_up" => count($passengersToBePickedUp),
                        "count_passengers_to_be_dropped_off" => count($passengersToBeDroppedOff),
                    ], 200);
                }
                else
                {
                    DB::commit();
                    return response()->json([
                        "success" => true,
                        "next_stop" => null,
                        "distance_to_next_stop" => 0,
                        "count_passengers_to_be_picked_up" => 0,
                        "passengers_to_be_dropped_off" => null,
                    ], 200);
                }
            }
            else
            {
                //get all stops on the planned trip from $planned_trip_details
                $stops = $planned_trip_details->map(function ($planned_trip_detail) {
                    return $planned_trip_detail->stop;
                });

                //add visited flag to each stop
                $stops = $stops->map(function ($stop) use($planned_trip_details) {
                    foreach ($planned_trip_details as $planned_trip_detail) {
                        if($planned_trip_detail->stop_id == $stop->id)
                        {
                            if($planned_trip_detail->actual_timestamp != null)
                            {
                                $stop->visited = 1;
                            }
                            else
                            {
                                $stop->visited = 0;
                            }
                            break;
                        }
                    }
                    return $stop;
                });

                //get ids of visited stops
                $visited_stops_ids = $stops->filter(function ($stop) {
                    return $stop->visited == 1;
                })->pluck('id')->toArray();
                $unvisited_stops = $stops->filter(function ($stop) {
                    return $stop->visited == 0;
                });
                // Log::info("visited_stops_ids");
                // Log::info($visited_stops_ids);
                //get closest stop to the current position
                $next_stop = $this->getClosestStop($lat, $lng, $unvisited_stops);
                // Log::info("next_stop");
                // Log::info($next_stop);
                $distance = $this->distance($lat, $lng, $next_stop->lat, $next_stop->lng)*1000;
                // Log::info("distance = " . $distance . ", distance_to_stop_to_mark_arrived = " . $distance_to_stop_to_mark_arrived);
                // if($distance < $distance_to_stop_to_mark_arrived)
                {
                    $planned_trip_detail = $planned_trip_details->filter(function ($planned_trip_detail) use($next_stop) {
                        return $planned_trip_detail->stop_id == $next_stop->id;
                    })->first();

                    if($planned_trip_detail->actual_timestamp != null)
                    {
                        DB::commit();
                        return response()->json([
                            "success" => true,
                            "next_stop" => null,
                            "distance_to_next_stop" => 0,
                            "count_passengers_to_be_picked_up" => 0,
                            "count_passengers_to_be_dropped_off" => 0,
                            "visited_stops_ids" => $visited_stops_ids,
                        ], 200);
                    }
                    $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]], ['*'], ['student.studentSettings']);

                    // filter $allPassengers based on ride_status
                    $passengersToBePickedUp = $allPassengers->filter(function ($passenger) use($next_stop) {
                        return $passenger->ride_status == 0 && $passenger->start_stop_id == $next_stop->id;
                    });

                    //loop through the passengersToBePickedUp
                    foreach ($passengersToBePickedUp as $passengerToBePickedUp) {
                        $student = $passengerToBePickedUp->student;
                        $studentSettings = $student->studentSettings;
                        //check notification settings
                        //bus_arrived_at_pickup_location_notification_on_off
                        if($studentSettings->bus_arrived_at_pickup_location_notification_on_off == 1)
                        {
                            $this->sendStudentNotificationBasedOnSetting("bus_arrived_at_pickup_location_notification_on_off", $student);
                        }
                    }
                    $passengersToBeDroppedOff = $allPassengers->filter(function ($passenger) use($next_stop) {
                        //check ride_status == 1 and drop off point is near the current position
                        return ($passenger->ride_status == 1) && $next_stop !=null &&
                        $passenger->end_stop_id == $next_stop->id;
                    });

                    //loop through the passengersToBeDroppedOff
                    foreach ($passengersToBeDroppedOff as $passengerToBeDroppedOff) {
                        $student = $passengerToBeDroppedOff->student;
                        $studentSettings = $student->studentSettings;
                        //check notification settings
                        //bus_arrived_at_drop_off_location_notification_on_off
                        if($studentSettings->bus_arrived_at_drop_off_location_notification_on_off == 1)
                        {
                            $this->sendStudentNotificationBasedOnSetting("bus_arrived_at_drop_off_location_notification_on_off", $student);
                        }
                    }
                    if (count($passengersToBePickedUp) == 0 && count($passengersToBeDroppedOff) == 0) {
                        $planned_trip_detail->actual_timestamp = Carbon::now();
                        $planned_trip_detail->save();
                        $next_stop = null;
                    }

                    DB::commit();
                    return response()->json([
                        "success" => true,
                        "next_stop" => $next_stop,
                        "next_stop_planned_time" => null,
                        "distance_to_next_stop" => $distance,
                        "count_passengers_to_be_picked_up" => count($passengersToBePickedUp),
                        "count_passengers_to_be_dropped_off" => count($passengersToBeDroppedOff),
                        "visited_stops_ids" => $visited_stops_ids,
                    ], 200);
                }
                // else
                // {
                //     DB::commit();
                //     return response()->json([
                //         "success" => true,
                //         "next_stop" => null,
                //         "distance_to_next_stop" => 0,
                //         "count_passengers_to_be_picked_up" => 0,
                //         "count_passengers_to_be_dropped_off" => 0,
                //         "visited_stops_ids" => $visited_stops_ids,
                //     ], 200);
                // }
            }
        } catch (\Exception $e) {
            Log::info("Exception" . $e->getMessage() . ", " . $e->getFile() . ", " . $e->getLine());
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function setLastPositionTest(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'planned_trip_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'speed' => 'required|numeric',
        ], [], []);

        $planned_trip_id = $request->planned_trip_id;

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user_id = 2;

        //create a transaction
        DB::beginTransaction();
        try {
            $lat = $request->lat;
            $lng = $request->lng;
            $speed = $request->speed;

            // Log::info("lat = " . $lat . ", lng = " . $lng . ", speed = " . $speed);

            $planned_trip->last_position_lat = $lat;
            $planned_trip->last_position_lng = $lng;

            $planned_trip->save();

            $pos = array(
                'speed' => $speed,
                'lat' => $planned_trip->last_position_lat,
                'lng' => $planned_trip->last_position_lng);

            broadcast(new \App\Events\TripPositionUpdated($planned_trip->channel, json_encode($pos)));
            //Log::info("TripPositionUpdated on channel " . $planned_trip->channel);

        } catch (\Exception $e) {
            Log::info("Exception" . $e->getMessage() . ", " . $e->getFile() . ", " . $e->getLine());
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


    private function getClosestStop($lat, $lng, $stops)
    {
        $closestDistance = INF;
        $closestStop = null;
        foreach ($stops as $stop) {
            $distance = $this->distance($lat, $lng, $stop->lat, $stop->lng);
            if ($distance < $closestDistance) {
                $closestStop = $stop;
                $closestDistance = $distance;
            }
        }
        return $closestStop;
    }

    private function getReservationsToBePickedUp($planned_trip_id)
    {
        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        //check if the user is the driver of the trip
        if ($planned_trip->driver_id != $user_id) {
            return response()->json(["message" => "Unauthorized", "success" => false], 401);
        }

        //loop through the plannedTripDetails stops
        $planned_trip_details = $planned_trip->plannedTripDetail;

        //get the next stop which do not have actual_timestamp
        $planned_trip_detail = $planned_trip_details->filter(function ($planned_trip_detail) {
            return $planned_trip_detail->actual_timestamp == null;
        })->first();

        if ($planned_trip_detail != null) {
            $next_stop = $planned_trip_detail->stop;

            $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);

            // filter $allPassengers based on ride_status
            $reservationsToBePickedUp = $allPassengers->filter(function ($passenger) use($next_stop) {
                return $passenger->ride_status == 0 && $passenger->start_stop_id == $next_stop->id;
            });

            return $reservationsToBePickedUp;
        }
        else
        {
            return null;
        }
    }

    //getStudentsToBePickedUp
    public function getStudentsToBePickedUp($planned_trip_id)
    {
        $reservationsToBePickedUp = $this->getReservationsToBePickedUp($planned_trip_id);

        //check if the reservationsToBePickedUp is a HttpResponse
        if ($reservationsToBePickedUp instanceof \Symfony\Component\HttpFoundation\Response) {
            return $reservationsToBePickedUp;
        }

        if($reservationsToBePickedUp == null)
        {
            return response()->json(null, 200);
        }

        $passengersToBePickedUpIds = $reservationsToBePickedUp->pluck('student_id')->toArray();
        $studentsToBePickedUp = $this->userRepository->findByWhereIn('id', $passengersToBePickedUpIds);

        return response()->json($studentsToBePickedUp, 200);
    }

    //getAllStudentsOnTrip
    public function getAllStudentsOnTrip($planned_trip_id)
    {
        $setting = $this->settingRepository->all()->first();
        $simple_mode = $setting->simple_mode;

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        //check if the user is the driver of the trip
        if ($planned_trip->driver_id != $user_id) {
            return response()->json(["message" => "Unauthorized", "success" => false], 401);
        }

        //loop through the plannedTripDetails stops
        $planned_trip_details = $planned_trip->plannedTripDetail;

        //get all passengers on the trip
        $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);

        $plannedStartTimes = $allPassengers->map(function ($passenger) {
            return $passenger->planned_start_time;
        })->toArray();
        $students = $allPassengers->map(function ($passenger) {
            return $this->userRepository->findById($passenger->student_id);
        });
        $startStops = $allPassengers->map(function ($passenger) {
            return $this->stopRepository->findById($passenger->start_stop_id);
        });
        $endStops = $allPassengers->map(function ($passenger) {
            return $this->stopRepository->findById($passenger->end_stop_id);
        });
        // $students = $this->userRepository->findByWhereIn('id', $passengersIds);
        // $startStops = $this->stopRepository->findByWhereIn('id', $startStopIds);
        // $endStops = $this->stopRepository->findByWhereIn('id', $endStopIds);

        //return
        return response()->json([
            "students" => $students,
            "start_stops" => $startStops,
            "end_stops" => $endStops,
            "planned_start_times" => $plannedStartTimes,
        ], 200);
    }

    //pick-up a passenger
    public function pickUp(Request $request)
    {
        //validate
        $this->validate($request, [
            'ticket_number' => 'required|nullable|string',
            'planned_trip_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'speed' => 'required|numeric',
            'missed' => 'nullable|integer',
        ], [], []);

        $user = Auth::user();
        $driver_id = $user->id;

        $setting = $this->settingRepository->all()->first();
        $distance_to_stop_to_mark_arrived = $setting->distance_to_stop_to_mark_arrived;

        $ticket_number = $request->ticket_number;
        $planned_trip_id = $request->planned_trip_id;
        $lat = $request->lat;
        $lng = $request->lng;
        $speed = $request->speed;

        Log::info("pickUp" . ", ticket_number = " . $ticket_number . ", planned_trip_id = " . $planned_trip_id . ", driver_id = " . $driver_id . ", lat = " . $lat . ", lng = " . $lng . ", speed = " . $speed . ", missed = " . $request->missed);

        if($ticket_number !== "null")
        {
            // $ticket_number="87654321";
            // get the student with the ticket_number
            $student = $this->userRepository->findByWhere([['student_identification', '=', $ticket_number]], ['*'], ['studentSettings'])->first();

            if ($student == null) {
                return response()->json(["message" => "Student not found", "success" => false], 404);
            }

            $reservation = $this->reservationRepository->findByWhere([['student_id', '=', $student->id], ['planned_trip_id', '=', $planned_trip_id], ['ride_status', '=', 0]],
            ['*'], ['firstStop', 'plannedTrip'])->first();

            Log::info("reservation" . $reservation);

            if ($reservation == null) {
                return response()->json(["message" => "Reservation not found", "success" => false], 404);
            }

            $planned_trip = $reservation->plannedTrip;

            if ($reservation->planned_trip_id != $planned_trip_id) {
                return response()->json(["message" => "Reservation not found", "success" => false], 404);
            }

            if ($planned_trip->driver_id != $driver_id) {
                return response()->json(["message" => "Unauthorized", "success" => false], 500);
            }

            if($reservation->ride_status != 0){
                return response()->json(["message" => "Passenger already picked up", "success" => false], 500);
            }

            if($request->missed != null && $request->missed == 1)
            {
                $distance = $this->distance($lat, $lng, $reservation->firstStop->lat, $reservation->firstStop->lng)*1000;
                if($distance < $distance_to_stop_to_mark_arrived){
                    $reservation->ride_status = 2; //missed
                    $reservation->save();
                    //get student
                    $student = $reservation->student;
                    $studentSettings = $student->studentSettings;
                    //student_is_missed_pickup_notification_on_off
                    if($studentSettings->student_is_missed_pickup_notification_on_off == 1)
                    {
                        $this->sendStudentNotificationBasedOnSetting("student_is_missed_pickup_notification_on_off", $student);
                    }
                    return $this->setLastPosition($request);
                }
                else
                {
                    return response()->json(["message" => "Passenger is not near the stop", "success" => false], 500);
                }
            }

            //check if the passenger is near the stop
            $distance = $this->distance($lat, $lng, $reservation->firstStop->lat, $reservation->firstStop->lng)*1000;

            if($distance > $distance_to_stop_to_mark_arrived){
                return response()->json(["message" => "Passenger is not near the stop", "success" => false], 500);
            }

            $reservation->ride_status = 1;
            $reservation->save();

            //student_is_picked_up_notification_on_off
            $studentSettings = $student->studentSettings;
            if($studentSettings->student_is_picked_up_notification_on_off == 1)
            {
                $this->sendStudentNotificationBasedOnSetting("student_is_picked_up_notification_on_off", $student);
            }

            // $this->updatePayment($reservation);

            return $this->setLastPosition($request);
        }
        else
        {
            //get all reservations for the planned_trip_id with ride_status = 0
            $reservations = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id], ['ride_status', '=', 0]],
            ['*'], ['firstStop', 'plannedTrip', 'student.studentSettings']);



            if ($reservations == null || count($reservations) == 0) {
                return response()->json(["message" => "Reservation not found", "success" => false], 404);
            }

            $reservation_found = false;

            // loop through the reservations and check if the passenger is near the stop
            foreach ($reservations as $reservation) {
                $planned_trip = $reservation->plannedTrip;
                if ($planned_trip->driver_id != $driver_id) {
                    return response()->json(["message" => "Unauthorized", "success" => false], 500);
                }

                $distance = $this->distance($lat, $lng, $reservation->firstStop->lat, $reservation->firstStop->lng)*1000;
                if($distance < $distance_to_stop_to_mark_arrived){
                    $reservation->ride_status = 2; //missed
                    $reservation->save();
                    $reservation_found = true;

                    //get student
                    $student = $reservation->student;
                    $studentSettings = $student->studentSettings;
                    //student_is_missed_pickup_notification_on_off
                    if($studentSettings->student_is_missed_pickup_notification_on_off == 1)
                    {
                        $this->sendStudentNotificationBasedOnSetting("student_is_missed_pickup_notification_on_off", $student);
                    }
                }
            }
            if($reservation_found == false){
                return response()->json(["message" => "No passenger found", "success" => false], 500);
            }

            // $this->updatePayment($reservation);

            return $this->setLastPosition($request);
        }
    }

    //dismissNextStop
    public function dismissNextStop(Request $request)
    {
        //validate
        $this->validate($request, [
            'planned_trip_id' => 'required|integer',
        ], [], []);

        $planned_trip_id = $request->planned_trip_id;

        $planned_trip = $this->plannedTripRepository->findById($planned_trip_id, ['*'], ['plannedTripDetail.stop']);
        if ($planned_trip == null) {
            return response()->json(["message" => "Trip not found", "success" => false], 404);
        }

        $user = Auth::user();
        $user_id = $user->id;

        //check if the user is the driver of the trip
        if ($planned_trip->driver_id != $user_id) {
            return response()->json(["message" => "Unauthorized", "success" => false], 401);
        }

        $planned_trip_details = $planned_trip->plannedTripDetail;
        //get the next stop which do not have actual_timestamp
        $planned_trip_detail = $planned_trip_details->filter(function ($planned_trip_detail) {
            return $planned_trip_detail->actual_timestamp == null;
        })->first();

        DB::beginTransaction();
        try {
            if ($planned_trip_detail != null) {
                $next_stop = $planned_trip_detail->stop;

                $planned_trip_detail->actual_timestamp = Carbon::now();
                $planned_trip_detail->save();

                $allPassengers = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id]]);

                // filter $allPassengers based on ride_status
                $reservationsToBePickedUp = $allPassengers->filter(function ($passenger) use($next_stop) {
                    return $passenger->ride_status == 0 && $passenger->start_stop_id == $next_stop->id;
                });
                // loop through the reservations and check if the passenger is near the stop
                foreach ($reservationsToBePickedUp as $reservation) {
                    $reservation->ride_status = 2; //missed
                    $reservation->save();

                    Log::info("dismissNextStop " . $reservation->student->name . " missed the stop " . $planned_trip_detail->stop->address . " on " . $planned_trip_detail->actual_timestamp);

                    //get student
                    $student = $reservation->student;
                    $studentSettings = $student->studentSettings;
                    //student_is_missed_pickup_notification_on_off
                    if($studentSettings->student_is_missed_pickup_notification_on_off == 1)
                    {
                        $this->sendStudentNotificationBasedOnSetting("student_is_missed_pickup_notification_on_off", $student);
                    }
                }
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->setLastPosition($request);
    }

    //drop-off a passenger
    public function dropOff(Request $request)
    {
        //validate
        $this->validate($request, [
            'planned_trip_id' => 'required|integer',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'speed' => 'required|numeric',
        ], [], []);

        $user = Auth::user();
        $driver_id = $user->id;

        $setting = $this->settingRepository->all()->first();
        $distance_to_stop_to_mark_arrived = $setting->distance_to_stop_to_mark_arrived;

        $planned_trip_id = $request->planned_trip_id;
        $lat = $request->lat;
        $lng = $request->lng;
        $speed = $request->speed;

        //get all reservations for the planned_trip_id with ride_status = 1
        $reservations = $this->reservationRepository->findByWhere([['planned_trip_id', '=', $planned_trip_id], ['ride_status', '=', 1]],
        ['*'], ['plannedTrip']);

        if ($reservations == null || count($reservations) == 0) {
            return response()->json(["message" => "Reservation not found", "success" => false], 404);
        }

        $reservation_found = false;

        // loop through the reservations and check if the passenger is near the stop
        foreach ($reservations as $reservation) {
            $planned_trip = $reservation->plannedTrip;
            if ($planned_trip->driver_id != $driver_id) {
                return response()->json(["message" => "Unauthorized", "success" => false], 500);
            }
            $distance = $this->distance($lat, $lng, $reservation->lastStop->lat, $reservation->lastStop->lng)*1000;
            if($distance < $distance_to_stop_to_mark_arrived){
                $reservation->ride_status = 3; //drop off
                $reservation->save();
                $reservation_found = true;
            }
        }
        if($reservation_found == false){
            return response()->json(["message" => "No drop-off passenger found", "success" => false], 500);
        }

        return $this->setLastPosition($request);

    }

    //notify
    public function notify(Request $request)
    {
        //validate
        $request->validate([
            'id' => 'required|integer',
            'message' => 'required|string',
        ]);

        $id = $request->id;

        //get the planned trip with all reservations
        $planned_trip = $this->plannedTripRepository->findById($id, ['*'], ['reservations.student.studentGuardians.guardian', 'driver']);

        // get all reservations for the planned_trip_id with ride_status = 0 or 1
        $reservations = $planned_trip->reservations->filter(function ($reservation) {
            return $reservation->ride_status == 0 || $reservation->ride_status == 1;
        });

        if (!($reservations == null || count($reservations) == 0)) {
            DB::beginTransaction();
            try {
                //get student of the reservations
                $students = $reservations->pluck('student');
                foreach ($students as $student) {
                    $this->sendNotificationToUser($student, $request->message);
                }
                //send to driver
                $this->sendNotificationToUser($planned_trip->driver, $request->message);
                Log::info("Notification sent to driver " . $planned_trip->driver->name . " message " . $request->message);
                DB::commit();
                return response()->json(['success'=> true], 200);
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        else
        {
            return response()->json(['message' => 'No passenger found', 'success'=> false], 404);
        }
    }
}
