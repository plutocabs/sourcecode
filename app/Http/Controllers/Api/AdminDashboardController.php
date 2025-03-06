<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repository\SettingRepositoryInterface;
use App\Repository\ChargeRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\RouteRepositoryInterface;
use App\Repository\StopRepositoryInterface;
use App\Repository\PlannedTripRepositoryInterface;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{

    private $settingRepository;
    private $userRepository;
    private $chargeRepository;
    private $routeRepository;
    private $stopRepository;
    private $planedTripRepository;


    public function __construct(
        SettingRepositoryInterface $settingRepository,
        ChargeRepositoryInterface $chargeRepository,
        UserRepositoryInterface $userRepository,
        RouteRepositoryInterface $routeRepository,
        StopRepositoryInterface $stopRepository,
        PlannedTripRepositoryInterface $planedTripRepository)
    {
        $this->settingRepository = $settingRepository;
        $this->chargeRepository = $chargeRepository;
        $this->userRepository = $userRepository;
        $this->routeRepository = $routeRepository;
        $this->stopRepository = $stopRepository;
        $this->planedTripRepository = $planedTripRepository;
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
        $user = auth()->user();

        $charges = $this->chargeRepository->allWhere(['*'], ['plan', 'school', 'parent']);
        $totalPaid = 0;
        $paidSchools = 0;
        $paidParents = 0;
        $totalSchools = 0;
        $totalParents = 0;
        // loop through the charges and add payer field
        foreach ($charges as $charge) {
            if($charge->plan == null)
            {
                continue;
            }
            if($charge->school_id != null)
            {
                $totalSchools++;
                $paidSchools += $charge->price;
            }
            else
            {
                $totalParents++;
                $paidParents += $charge->price;
            }
            $totalPaid += $charge->price;
        }

        //group the charges by plan_id
        $charges = $charges->groupBy('plan_id');
        //count the charges of each plan
        $charges = $charges->map(function ($item, $key) {
            if($item[0]->plan != null)
            {
                return [
                    'plan_id' => $key,
                    'count' => $item->count(),
                    'total' => $item->sum('price'),
                    'plan_name' => $item[0]->plan->name,
                    'plan_type' => $item[0]->plan->plan_type,
                ];
            }
        });

        //get the largest 5 plans
        $bestSalesPlans = $charges->sortByDesc('total')->take(5);
        //remove nulls from the array
        $bestSalesPlans = $bestSalesPlans->filter(function ($value, $key) {
            return $value != null;
        });
        $bestSalesPlans = $bestSalesPlans->values()->all();
        Log::info($bestSalesPlans);
        // if(count($bestSalesPlans) < 5) {
        //     $bestSalesPlans = [];
        // }

        //get currency symbol
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        //approximate the total reservations amount to 2 decimal places
        $totalPaid = number_format($totalPaid, 2);
        $totalPaid = $totalPaid . ' ' . $currency_code;

        $paidSchools = number_format($paidSchools, 2);
        $paidSchools = $paidSchools . ' ' . $currency_code;

        $paidParents = number_format($paidParents, 2);
        $paidParents = $paidParents . ' ' . $currency_code;

        //count schools
        $schools = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 2]]);
        $totalSchools = $schools->count();

        //count parents
        $parents = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 3]]);
        $totalParents = $parents->count();
        //count guardians
        $guardians = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 5]]);
        $totalGuardians = $guardians->count();

        $totalGuardians = $totalGuardians + $totalParents;

        //count drivers
        $drivers = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 2]]);
        $totalDrivers = $drivers->count();

        //count students
        $students = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 4]]);
        $totalStudents = $students->count();

        //count routes
        $routes = $this->routeRepository->all();
        $totalRoutes = $routes->count();

        //count stops
        $stops = $this->stopRepository->all();
        $totalStops = $stops->count();


        $plannedTrips = $this->planedTripRepository->all();
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

        $dashboardStats = [
            'totalPaid' => $totalPaid,
            'paidSchools' => $paidSchools,
            'paidParents' => $paidParents,
            'totalSchools' => $totalSchools,
            'totalGuardians' => $totalGuardians,
            'totalDrivers' => $totalDrivers,
            'totalStudents' => $totalStudents,
            'totalRoutes' => $totalRoutes,
            'totalStops' => $totalStops,
            'bestSalesPlans' => $bestSalesPlans,
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
