<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Repository\ReservationRepositoryInterface;
use App\Repository\UserRefundRepositoryInterface;
use App\Repository\UserPaymentRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\SettingRepositoryInterface;
use App\Repository\DriverInformationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReservationController extends Controller
{
    private $reservationRepository;
    private $userRefundRepository;
    private $userPaymentRepository;
    private $userRepository;
    private $settingRepository;
    private $driverInformationRepository;

    public function __construct(
        ReservationRepositoryInterface $reservationRepository,
        UserRefundRepositoryInterface $userRefundRepository,
        UserPaymentRepositoryInterface $userPaymentRepository,
        UserRepositoryInterface $userRepository,
        SettingRepositoryInterface $settingRepository,
        DriverInformationRepositoryInterface $driverInformationRepository)
    {
        $this->reservationRepository = $reservationRepository;
        $this->userRefundRepository = $userRefundRepository;
        $this->userPaymentRepository = $userPaymentRepository;
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
        $this->driverInformationRepository = $driverInformationRepository;
    }

    public function index()
    {

        $school = Auth::user();

        $reservation = $this->reservationRepository->all(
            ['*'], ['firstStop', 'lastStop', 'plannedTrip.route', 'student', 'plannedTrip.driver']);

        //filter reservations by school
        $reservations = [];
        foreach ($reservation as $res) {
            if ($res->plannedTrip->route != null && $res->plannedTrip->route->school_id == $school->id) {
                array_push($reservations, $res);
            }
        }



        //filter reservations by ride_status

        $activeReservations = [];
        $rideReservations = [];
        $missedReservations = [];
        $completedReservations = [];
        $cancelledReservations = [];

        foreach ($reservations as $res) {
            //if the ride_status is 0, then it is active
            if ($res->ride_status == 0) {
                array_push($activeReservations, $res);
            } else if ($res->ride_status == 1) {
                //if the ride_status is 1, then it is ride (on-road)
                array_push($rideReservations, $res);
            } else if ($res->ride_status == 2) {
                //if the ride_status is 2, then it is missed
                array_push($missedReservations, $res);
            } else if ($res->ride_status == 3) {
                //if the ride_status is 3, then it is completed
                array_push($completedReservations, $res);
            }
            else if ($res->ride_status == 4) {
                //if the ride_status is 4, then it is cancelled
                array_push($cancelledReservations, $res);
            }

        }

        $reservations = [
            'active' => $activeReservations,
            'ride' => $rideReservations,
            'missed' => $missedReservations,
            'completed' => $completedReservations,
            'cancelled' => $cancelledReservations
        ];

        return response()->json($reservations, 200);
    }

    //cancel
    public function cancel(Request $request)
    {
        //validate
        $request->validate([
            'id' => 'required|integer',
            'reason' => 'required|string',
        ]);

        $reservation = $this->reservationRepository->findById($request->id, ['*'], ['customer', 'plannedTrip.driver']);

        if ($reservation) {

            if ($reservation->ride_status == 4) {
                return response()->json(['message' => 'Reservation already cancelled'], 400);
            }

            // // This is optional, but it's a good idea to check if the customer already used the ride
            // //make sure that the ride_status is 0
            // if ($reservation->ride_status != 0) {
            //     return response()->json(['message' => 'Reservation cannot be cancelled'], 400);
            // }

            $reservation_ride_status = $reservation->ride_status;

            //make transaction, set ride_status to 4 and deduct from wallet and add to admin wallet
            DB::beginTransaction();
            try {
                $customer = $reservation->customer;
                $customer->wallet += $reservation->paid_price;
                $customer->save();

                $reservation->ride_status = 4;
                $reservation->save();

                $returnedSuccessMessage = 'Reservation cancelled successfully';
                $adminRedeem = false;
                $driverRedeem = false;
                if($reservation_ride_status >= 1 && $reservation_ride_status <= 3)
                {
                    //deduct from admin wallet
                    $admin = $this->userRepository->allWhere(['*'], [], ['role' => 0], false)->first();
                    $admin->wallet -= $reservation->admin_share;
                    $admin->save();

                    //deduct from driver wallet
                    $driver = $reservation->plannedTrip->driver;
                    $driver->wallet -= $reservation->driver_share;
                    $driver->save();

                    $reservationPayments = $this->userPaymentRepository->allWhere(['*'], [], ['reservation_id' => $reservation->id], false);

                    foreach ($reservationPayments as $payment) {
                        if($payment->user_id == $admin->id)
                        {
                            if($payment->redeemed == 1)
                            {
                                $adminRedeem = true;
                            }
                        }
                        else if($payment->user_id == $driver->id)
                        {
                            if($payment->redeemed == 1)
                            {
                                $driverRedeem = true;
                            }
                        }
                    }
                    //delete from $this->userPaymentRepository
                    $this->userPaymentRepository->deleteWhere(['reservation_id' => $reservation->id]);

                    //get the current currency
                    $currency = $this->settingRepository->all(['*'], ['currency'])->first();
                    $currency_code = $currency->currency->code;

                    if($adminRedeem)
                    {
                        $returnedSuccessMessage = $returnedSuccessMessage . '. Please note that the admin payment has been redeemed. Please contact the payment provider of the admin to refund the admin payment of ' . round($reservation->admin_share, 2) . ' ' .
                        $currency_code;
                    }
                    if($driverRedeem)
                    {
                        $returnedSuccessMessage = $returnedSuccessMessage . '. Please note that the driver payment has been redeemed. Please contact the payment provider of the driver to refund the driver payment of ' . round($reservation->driver_share, 2) . ' ' . $currency_code;
                    }
                }

                $this->userRefundRepository->create([
                    'user_id' => $customer->id,
                    'amount' => $reservation->paid_price,
                    'reason' => $request->reason,
                    'reservation_id' => $reservation->id,
                    'refund_date' => date('Y-m-d'),
                ]);

                DB::commit();
                return response()->json(['message' => $returnedSuccessMessage], $adminRedeem || $driverRedeem ? 201 : 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => 'Reservation not cancelled'], 500);
            }
        } else {
            return response()->json(['message' => 'Reservation not found'], 404);
        }
    }

    //getReservationDetails
    public function getReservationDetails(Request $request)
    {
        //validate
        $request->validate([
            'student_id' => 'required|integer',
            'morning' => 'required|boolean',
        ]);
        $student_id = $request->student_id;
        $morning = $request->morning;
        // get the reservations by student id
        $reservations = $this->reservationRepository->allWhere(['*'], ['plannedTrip.route.stops', 'plannedTrip.route.routeStops.routeStopDirections', 'plannedTrip.driver'], ['student_id' => $student_id], true);

        // get the latest two
        $latestReservations = $reservations->take(2);
        // mark one as morning and the other as evening based on the route type
        $morningReservation = null;
        $eveningReservation = null;
        if (count($latestReservations) > 0) {
            foreach ($latestReservations as $key => $reservation) {
                if($reservation->plannedTrip == null || $reservation->plannedTrip->route == null)
                {
                    continue;
                }
                if($reservation->plannedTrip->route->is_morning == 1 && $morningReservation == null)
                {
                    $morningReservation = $reservation;
                    // Log::info("morning reservation");
                    // Log::info($morningReservation);
                }
                else if($reservation->plannedTrip->route->is_morning == 0 && $eveningReservation == null)
                {
                    $eveningReservation = $reservation;
                    // Log::info("evening reservation");
                    // Log::info($eveningReservation);
                }
            }
        }
        $reservation = null;
        if($morning)
        {
            $reservation = $morningReservation;
        }
        else
        {
            $reservation = $eveningReservation;
        }
        if ($reservation) {
            $route = $reservation->plannedTrip->route;
            //decode the overview_path for each route direction
            $directions = [];
            $distance = 0;
            foreach ($route->routeStops as $routeStop) {
                if(count($routeStop->routeStopDirections) > 0) {
                    $routeDirections = [];
                    foreach ($routeStop->routeStopDirections as $key => $route_stop_direction) {
                        $path = json_decode($route_stop_direction->overview_path);
                        $d = [
                            'summary' => $route_stop_direction->summary,
                            'current' => $route_stop_direction->current,
                            'index' => $route_stop_direction->index,
                            'overview_path' => $path
                        ];
                        array_push($routeDirections, $d);
                    }
                    array_push($directions, $routeDirections);
                }
            }
            $route->directions = $directions;
            $reservation->route_details = $route;
            if($reservation->plannedTrip->driver->tel_number == null)
            {
                //get driver information
                $driverInformation = $this->driverInformationRepository->findByWhere(['driver_id' => $reservation->plannedTrip->driver->id])->first();
                if($driverInformation)
                {
                    $reservation->plannedTrip->driver->tel_number = $driverInformation->phone_number;
                }
            }
            return response()->json(['reservation' => $reservation], 200);
        } else {
            return response()->json(['message' => 'Student trip not found', 'reservation' => null], 200);
        }
    }
}
