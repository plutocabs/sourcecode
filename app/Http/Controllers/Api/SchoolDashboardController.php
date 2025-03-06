<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repository\SettingRepositoryInterface;
use App\Repository\ReservationRepositoryInterface;
use App\Repository\UserPaymentRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\RouteRepositoryInterface;
use App\Repository\StopRepositoryInterface;
use App\Repository\TripRepositoryInterface;
use App\Repository\UserRefundRepositoryInterface;
use App\Repository\PlannedTripRepositoryInterface;
use App\Repository\ChargeRepositoryInterface;
use App\Repository\ConsumptionRepositoryInterface;
use Illuminate\Support\Facades\Log;

class SchoolDashboardController extends Controller
{

    private $settingRepository;
    private $reservationRepository;
    private $userPaymentRepository;
    private $userRepository;
    private $routeRepository;
    private $stopRepository;
    private $tripRepository;
    private $userRefundRepository;
    private $planedTripRepository;
    private $chargeRepository;
    private $consumptionRepository;

    public function __construct(
        SettingRepositoryInterface $settingRepository,
        ReservationRepositoryInterface $reservationRepository,
        UserPaymentRepositoryInterface $userPaymentRepository,
        UserRepositoryInterface $userRepository,
        RouteRepositoryInterface $routeRepository,
        StopRepositoryInterface $stopRepository,
        TripRepositoryInterface $tripRepository,
        UserRefundRepositoryInterface $userRefundRepository,
        PlannedTripRepositoryInterface $planedTripRepository,
        ChargeRepositoryInterface $chargeRepository,
        ConsumptionRepositoryInterface $consumptionRepository)
    {
        $this->settingRepository = $settingRepository;
        $this->reservationRepository = $reservationRepository;
        $this->userPaymentRepository = $userPaymentRepository;
        $this->userRepository = $userRepository;
        $this->routeRepository = $routeRepository;
        $this->stopRepository = $stopRepository;
        $this->tripRepository = $tripRepository;
        $this->userRefundRepository = $userRefundRepository;
        $this->planedTripRepository = $planedTripRepository;
        $this->chargeRepository = $chargeRepository;
        $this->consumptionRepository = $consumptionRepository;
    }

    //thousands format
    private function thousandsFormat($num) {

        if($num>1000) {

              $x = round($num);
              $x_number_format = number_format($x);
              $x_array = explode(',', $x_number_format);
              $x_parts = array('k', 'm', 'b', 't');
              $x_count_parts = count($x_array) - 1;
              $x_display = $x;
              $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
              $x_display .= $x_parts[$x_count_parts - 1];

              return $x_display;

        }

        return $num;
      }

    // index
    public function index()
    {
        //get the user
        $school = auth()->user();

        //get the current currency
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;


        $school_id = $school->id;

        $school = $this->userRepository->findByWhere([['id', '=', $school_id]], ['*'], ['schoolStudents.studentGuardians.guardian'])->first();

        //get all students of the school
        $students = $school->schoolStudents;
        // get all guardians of the students
        $guardians = $students->map(function ($student) {
            return $student->studentGuardians->map(function ($studentGuardian) {
                return $studentGuardian->guardian;
            });
        });
        //filter redundant guardians
        $guardians = $guardians->flatten()->unique('id');
        $totalGuardians = $guardians->count();

        $totalStudents = $students->count();

        //count routes of the school
        $routes = $this->routeRepository->allWhere(['*'], [], ['school_id' => $school_id]);
        $totalRoutes = $routes->count();

        //count stops of the school
        $stops = $this->stopRepository->allWhere(['*'], [], ['school_id' => $school_id]);
        $totalStops = $stops->count();

        //count trips of the school
        $trips = $this->tripRepository->allWhere(['*'], [], ['school_id' => $school_id]);
        $totalTrips = $trips->count();

        //count drivers of the school
        $drivers = $this->userRepository->findByWhere([['role_id', '=', 3], ['school_id', '=', $school_id]], ['*']);
        $totalDrivers = $drivers->count();

        $charges = $this->chargeRepository->allWhere(['*'], [], ['school_id' => $school_id]);
        $purchasedCoins = 0;
        // loop through the charges and sum
        foreach ($charges as $charge) {
            $purchasedCoins += $charge->coin_count;
        }

        // consumed coins count
        $consumedCoins = 0;
        $consumptions = $this->consumptionRepository->allWhere(['*'], [], ['user_id' => $school_id]);
        foreach ($consumptions as $consumption) {
            $consumedCoins += $consumption->amount;
        }

        $remainingCoins = $school->balance;


        $plannedTrips = $this->planedTripRepository->allWhere(['*'], ['trip'])->where('trip.school_id', $school_id);
        //group the planned trips by planned_date
        $plannedTrips = $plannedTrips->groupBy('planned_date');

        //get all planned trips with planned_date in a week from now
        $plannedTripsFutureWeek = $plannedTrips->filter(function ($value, $key) {
            return $value[0]->planned_date >= date('Y-m-d') && $value[0]->planned_date <= date('Y-m-d', strtotime('+7 days'));
        });


        $plannedTripsAll = $plannedTripsFutureWeek;
        //count the $plannedTripsFutureWeek. If it is less than 7, then fill it from the past
        if ($plannedTripsFutureWeek->count() < 7) {
            $remainingCount = 7 - $plannedTripsFutureWeek->count();

            //get only remainingCount planned trips from the past
            $plannedTripsPast = $plannedTrips->filter(function ($value, $key) {
                return $value[0]->planned_date < date('Y-m-d');
            });

            $plannedTripsPast = $plannedTripsPast->take($remainingCount);

            //merge the past and future planned trips
            $plannedTripsAll = $plannedTripsAll->toBase()->merge($plannedTripsPast);
        }

        //store only the count of each day in $plannedTripsAll
        $plannedTripsAll = $plannedTripsAll->map(function ($item, $key) {
            return $item->count();
        });

        //remove the year from the key
        $plannedTripsAll = $plannedTripsAll->mapWithKeys(function ($item, $key) {
            return [date('m-d', strtotime($key)) => $item];
        });

        //order the array by key
        $plannedTripsAll = $plannedTripsAll->sortKeys();

        if(count($plannedTripsAll) < 5) {
            $plannedTripsAll = [];
        }

        //get total reservations
        $reservations = $this->reservationRepository->all(['*'], ['plannedTrip', 'plannedTrip.route', 'plannedTrip.trip']);
        //remove reservations that do not belong to the school
        $reservations = $reservations->filter(function ($value, $key) use ($school_id) {
            return $value->plannedTrip->trip->school_id == $school_id;
        });
        // get the best sales trips
        // group reservations by plannedTrip.trip.ID
        $reservations = $reservations->groupBy('plannedTrip.trip.id');
        //count the reservations of each trip
        $reservations = $reservations->map(function ($item, $key) {
            return $item->count();
        });
        //sort the trips by reservations count
        $reservations = $reservations->sortDesc();
        // get the top 5 trips
        $reservations = $reservations->take(5);
        $bestSalesTrips = [];
        foreach ($reservations as $key => $value) {
            $trip = $this->tripRepository->findById($key, ['*'], ['route']);
            if($trip->route != null)
            {
                $bestSalesTrips[] = [
                    'id' => $trip->id,
                    'route' => $trip->route->name,
                    'time' => $trip->first_stop_time . ' - ' . $trip->last_stop_time,
                    'repetition' => $this->getRepetitionPeriod($trip->repetition_period),
                    'sales' => $this->thousandsFormat($value),
                    'is_morning' => $trip->route->is_morning,
                ];
            }
        }
        //check the length of bestSalesTrips array
        // if it is less than 5, then fill it with empty arrays
        // if (count($bestSalesTrips) < 5) {
        //     $bestSalesTrips = [];
        // }

        $dashboardStats = [
            'purchasedCoins' => $purchasedCoins,
            'consumedCoins' => $consumedCoins,
            'remainingCoins' => $remainingCoins,
            'totalStudents' => $this->thousandsFormat($totalStudents),
            'totalGuardians' => $this->thousandsFormat($totalGuardians),
            'totalDrivers' => $this->thousandsFormat($totalDrivers),
            'totalRoutes' => $this->thousandsFormat($totalRoutes),
            'totalStops' => $this->thousandsFormat($totalStops),
            'totalTrips' => $this->thousandsFormat($totalTrips),
            'bestTrips' => $bestSalesTrips,
            'plannedTrips' => $plannedTripsAll,
        ];

        return response()->json($dashboardStats, 200);
    }

    private function getRepetitionPeriod($period)
    {
        switch ($period) {
            case 0:
                return 'Once';
                break;
            case 1:
                return 'Daily';
                break;
            default:
                return 'Every ' . $period . ' days';
                break;
        }
    }
}
