<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Repository\TripRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\BusRepositoryInterface;
use App\Repository\DriverInformationRepositoryInterface;
use App\Repository\DriverDocumentRepositoryInterface;
use App\Repository\PlannedTripRepositoryInterface;
use App\Repository\UserPaymentRepositoryInterface;
use App\Repository\RedemptionRepositoryInterface;
use App\Repository\BankAccountRepositoryInterface;
use App\Repository\PaypalAccountRepositoryInterface;
use App\Repository\MobileMoneyAccountRepositoryInterface;
use App\Repository\SettingRepositoryInterface;

use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\Constraint\IsEmpty;
use DB;
use App\Traits\DriverUtils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use \File;
class DriverController extends Controller
{
    use DriverUtils;
    private $userRepository;
    private $tripRepository;
    private $plannedTripRepository;
    private $busRepository;
    private $driverInformationRepository;
    private $driverDocumentRepository;
    private $userPaymentRepository;
    private $redemptionRepository;
    private $bankAccountRepository;
    private $paypalAccountRepository;
    private $mobileMoneyAccountRepository;
    private $settingRepository;



    public function __construct(
        UserRepositoryInterface $userRepository,
        UserPaymentRepositoryInterface $userPaymentRepository,
        RedemptionRepositoryInterface $redemptionRepository,
        TripRepositoryInterface $tripRepository,
        PlannedTripRepositoryInterface $plannedTripRepository,
        BusRepositoryInterface $busRepository,
        DriverInformationRepositoryInterface $driverInformationRepository,
        DriverDocumentRepositoryInterface $driverDocumentRepository,
        BankAccountRepositoryInterface $bankAccountRepository,
        PaypalAccountRepositoryInterface $paypalAccountRepository,
        MobileMoneyAccountRepositoryInterface $mobileMoneyAccountRepository,
        SettingRepositoryInterface $settingRepository)
    {
        $this->userRepository = $userRepository;
        $this->userPaymentRepository = $userPaymentRepository;
        $this->redemptionRepository = $redemptionRepository;
        $this->tripRepository = $tripRepository;
        $this->plannedTripRepository = $plannedTripRepository;
        $this->busRepository = $busRepository;
        $this->driverInformationRepository = $driverInformationRepository;
        $this->driverDocumentRepository = $driverDocumentRepository;
        $this->bankAccountRepository = $bankAccountRepository;
        $this->paypalAccountRepository = $paypalAccountRepository;
        $this->mobileMoneyAccountRepository = $mobileMoneyAccountRepository;
        $this->settingRepository = $settingRepository;
    }

    //getDriverConflicts
    public function getDriverConflicts(Request $request)
    {
        $school = Auth::user();
        //get all active drivers
        $drivers = $this->userRepository->allWhere(
            ['*'], ['trips.route'], [['role_id', '=', 3], ['status_id', '=', 1], ['school_id', '=', $school->id]]
        );

        $conflicts = [];

        foreach ($drivers as $key_driver => $driver) {

            if(!$driver->trips)
            {
                continue;
            }

            if (count($driver->trips) == 0 || count($driver->trips) == 1)
            {
                continue;
            }
            // Log::info('Driver ' . $driver->name . ' has ' . count($driver->trips) . ' trips with IDS = ' . $driver->trips->pluck('id'));
            $currentTrip = $driver->trips[0];
            //loop through all trips of this driver
            for ($i=1; $i < count($driver->trips); $i++) {
                $otherTrip = $driver->trips[$i];

                $tripIntersect = $this->isDriverAvailable($driver, $otherTrip);
                if($tripIntersect != null && $tripIntersect['x'] != -1)
                {
                    // Log::info('Driver ' . $driver->name . ' is not available as he is assigned to trip ' . $tripIntersect['trip']->id . ' on ' . $tripIntersect['intersect_date'] . ' with x = ' . $tripIntersect['x'] . ' and tripStart = ' . $tripIntersect['tripStart'] . ' and newTripStart = ' . $tripIntersect['newTripStart'] . ' and trip_repetition = ' . $tripIntersect['trip']->repetition_period. ' and newTrip_repetition = ' . $tripIntersect['newTrip']->repetition_period . ' and effective date = ' . $tripIntersect['trip']->effective_date . ' and new trip effective date = ' . $tripIntersect['newTrip']->effective_date);
                    if($currentTrip->route == null || $otherTrip->route == null)
                    {
                        continue;
                    }
                    if($currentTrip->route->is_morning != $otherTrip->route->is_morning)
                    {
                        continue;
                    }
                    //add to conflicts
                    $conflict = [
                        'driver' => $driver,
                        'current_trip' => $currentTrip,
                        'other_trip' => $otherTrip,
                        'intersect_date' => $tripIntersect['intersect_date'],
                    ];
                    array_push($conflicts, $conflict);
                }
            }
        }

        return response()->json(['conflicts' => $conflicts], 200);
    }

    public function getAvailableDrivers(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'trip_id' => 'required|integer',
        ], [], []);

        $availableDrivers = [];

        $trip_id = $request->trip_id;
        $school = Auth::user();
        $trip = $this->tripRepository->allWhere(['*'], ['driver', 'route'], [['id', '=', $trip_id], ['status_id', '=', 1], ['school_id', '=', $school->id]])->first();
        //$trashedTrips = $this->tripRepository->allWhere(['*'], ['route', 'driver'], [['status_id', '=', 3]]);
        //$suspensions = $this->suspendedTripRepository->allWhere(['*'], ['trip', 'trip.route']);
        if($trip == null)
        {
            return response()->json(['error' => ['Trip does not exist']], 422);
        }

        //get drivers with assigned buses only in this school
        $drivers = $this->userRepository->allWhere(
            ['*'], ['trips.route', 'bus'], [['role_id', '=', 3], ['school_id', '=', $school->id]]
        );


        foreach ($drivers as $key => $driver) {
            if($driver->bus == null)
            {
                continue;
            }
            $driver->trip_intersect_date = null;
            $driver->trip_intersect = null;
            $tripIntersect = $this->isDriverAvailable($driver, $trip);
            if($tripIntersect != null && $tripIntersect['x'] != -1)
            {
                if($tripIntersect['trip']->route == null || $tripIntersect['newTrip']->route == null)
                {
                    continue;
                }
                Log::info('Driver ' . $driver->name . ' is not available as he is assigned to trip ' . $tripIntersect['trip']->id . ' on ' . $tripIntersect['intersect_date'] . ' with x = ' . $tripIntersect['x'] . ' and tripStart = ' . $tripIntersect['tripStart'] . ' and newTripStart = ' . $tripIntersect['newTripStart'] . ' and trip_repetition = ' . $tripIntersect['trip']->repetition_period. ' and newTrip_repetition = ' . $tripIntersect['newTrip']->repetition_period . ' and effective date = ' . $tripIntersect['trip']->effective_date . ' and new trip effective date = ' . $tripIntersect['newTrip']->effective_date);
                //show when the trips intersect
                $tripIntersectDate = $tripIntersect['intersect_date'];
                $driver->trip_intersect_date = $tripIntersectDate;
                $driver->trip_intersect = $tripIntersect['trip'];
            }
            array_push($availableDrivers, $driver);
        }
        return response()->json(['availableDrivers' => $availableDrivers, 'trip' => $trip], 200);
    }

    //load getAvailableBuses
    public function getAvailableBuses(Request $request)
    {
        $school = Auth::user();
        //validate the request
        $availableBuses = $this->busRepository->allWhere(
            ['*'], [], [['driver_id', '=', null], ['school_id', '=', $school->id]]
        );
        return response()->json($availableBuses, 200);
    }

    public function getAllBuses(Request $request)
    {
        $school = Auth::user();
        $school_id = $school->id;

        $availableBuses = $this->busRepository->allWhere(
            ['*'], [], [['driver_id', '!=', null], ['school_id', '=', $school->id]]
        );

        $school = $this->userRepository->findById($school_id, ['*'], ['schoolStudents.studentSettings', 'schoolSettings']);
        $students = $school->schoolStudents;
        //add available seats to each bus
        foreach ($availableBuses as $key => $bus) {
            $takenMorningSeats = 0;
            $takenAfternoonSeats = 0;
            foreach ($students as $key => $student) {
                if($student->studentSettings == null)
                {
                    continue;
                }
                if($student->studentSettings->morning_bus_id == $bus->id)
                {
                    $takenMorningSeats++;
                }
                if($student->studentSettings->afternoon_bus_id == $bus->id)
                {
                    $takenAfternoonSeats++;
                }
            }
            $bus->available_morning_seats = $bus->capacity - $takenMorningSeats;
            $bus->available_afternoon_seats = $bus->capacity - $takenAfternoonSeats;
        }
        return response()->json($availableBuses, 200);
    }

    //assignBus
    public function assignBus(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'driver_id' => 'required|integer',
            'bus_id' => 'required|integer',
        ], [], []);
        $driver_id = $request->driver_id;
        $bus_id = $request->bus_id;
        $school = Auth::user();
        $driver = $this->userRepository->findByWhere(['id' => $driver_id, 'school_id' => $school->id, 'role_id' => 3], ['*'], ['bus'])->first();
        if(!$driver)
        {
            return response()->json(['error' => ['Driver does not exist']], 422);
        }
        $bus = $this->busRepository->findByWhere(['id' => $bus_id, 'school_id' => $school->id], ['*'], [])->first();
        if(!$bus)
        {
            return response()->json(['error' => ['Bus does not exist']], 422);
        }
        //check if bus is available
        if($bus->driver_id != null)
        {
            return response()->json(['error' => ['Bus is not available']], 422);
        }
        //create transaction
        DB::beginTransaction();
        try {
            //unassign driver from bus first
            if($driver->bus)
            {
                $driver->bus->driver_id = null;
                $driver->bus->save();
            }
            $bus->driver_id = $driver->id;
            $bus->save();
            DB::commit();
            return response()->json(['success' => ['Bus assigned successfully']], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //unAssignBus
    public function unAssignBus(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'driver_id' => 'required|integer',
        ], [], []);
        $driver_id = $request->driver_id;
        $school = Auth::user();
        $driver = $this->userRepository->findByWhere(['id' => $driver_id, 'school_id' => $school->id, 'role_id' => 3], ['*'], ['bus'])->first();
        if(!$driver)
        {
            return response()->json(['error' => ['Driver does not exist']], 422);
        }
        //check if bus is available
        if($driver->bus == null)
        {
            return response()->json(['error' => ['Driver is not assigned to any bus']], 422);
        }
        //create transaction
        DB::beginTransaction();
        try {
            $driver->bus->driver_id = null;
            $driver->bus->save();
            DB::commit();
            return response()->json(['success' => ['Bus unassigned successfully']], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //saveDriverInfo
    public function saveDriverInfo(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //validate the request, make sure it contains submit flag
        $this->validate($request, [
            'submit' => 'required|boolean',
        ], [], []);

        $validationRules = [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'address' => 'required|string',
            'email' => 'required|email',
            'license_number' => 'required|string',
            'school_id' => 'required|integer',
        ];
        if($request->submit)
        {
            //append to validationRules driver documents
            $validationRules['documents'] = 'nullable|array';
            $validationRules['documents.*.document_name'] = 'required|string';
            $validationRules['documents.*.document_number'] = 'required|string';
            $validationRules['documents.*.expiry_date'] = 'required|date';
            $validationRules['documents.*.document_image'] = 'required|string';

        }

        $this->validate($request, $validationRules, [], []);

        //create transaction
        DB::beginTransaction();
        try {
            $driverInformationData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'email' => $request->email,
                'license_number' => $request->license_number,
                'driver_id' => $user_id,
            ];
            $schoolId = $request->school_id;
            $school = $this->userRepository->findById($schoolId);
            // check if school exists
            if(!$school)
            {
                return response()->json(['error' => ['School does not exist']], 422);
            }

            //check if driver information exists
            $driverInformation = $this->driverInformationRepository->findByWhere(['driver_id' => $user_id])->first();
            if($driverInformation)
            {
                //update driver information
                $driverInformation->update($driverInformationData);
                if($request->submit)
                {
                    //delete all driver documents
                    $this->driverDocumentRepository->deleteWhere(['driver_information_id' => $driverInformation->id]);
                }
            }
            else
            {
                //save driver information
                $driverInformation = $this->driverInformationRepository->create($driverInformationData);
            }

            //check if request contains documents
            if($request->submit)
            {
                if(isset($request->documents) && count($request->documents) > 0)
                {
                    foreach ($request->documents as $key => $driver_document) {
                        $driverDocument = $this->driverDocumentRepository->create([
                            'driver_information_id' => $driverInformation->id,
                            'document_name' => $driver_document['document_name'],
                            'document_number' => $driver_document['document_number'],
                            'expiry_date' => $driver_document['expiry_date'],
                            'local_file_path' => $driver_document['local_file_path'],
                        ]);
                        $image = $driver_document['document_image'];
                        //from base64 to image
                        $image = str_replace('data:image/png;base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $image = base64_decode($image);
                        //save document image
                        $imageName = time(). '.'. $driver_document['document_name'] . '.jpg';
                        $destinationPath = Storage::url('images/'. $user_id. '/driver_documents');
                        $destinationAbsolutePath = public_path('/backend'.$destinationPath);
                        if(!File::exists($destinationAbsolutePath)) {
                            File::makeDirectory($destinationAbsolutePath, 0777, true, true);
                        }
                        //save image to destination path
                        // $image->move($destinationAbsolutePath, $imageName);
                        file_put_contents($destinationAbsolutePath . '/' . $imageName, $image);
                        $driverDocument->remote_file_path = $destinationPath . '/' . $imageName;
                        $driverDocument->save();
                    }
                }
            }

            //save driver documents
            if($request->submit)
            {
                //change user status to under review
                $user->status_id = 4;
                $user->school_id = $schoolId;
                $user->save();
            }

            DB::commit();
            return response()->json(['success' => ['Driver information saved successfully']], 200);
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //getDriverInfo

    public function getDriverInfo(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //get driver information
        $driverInformation = $this->driverInformationRepository->findByWhere(['driver_id' => $user_id])->first();
        if(!$driverInformation)
        {
            return response()->json(['error' => ['Driver information does not exist']], 422);
        }

        //get driver documents
        $driverDocuments = $this->driverDocumentRepository->findByWhere(['driver_information_id' => $driverInformation->id]);

        $driverInformation->documents = $driverDocuments;

        //get user info with school
        $userInfo = $this->userRepository->findById($user_id, ['*'], ['driverSchool']);

        $settings = $this->settingRepository->all(
            ['*'], ['currency']
        )->first();
        $settings->currency_code = $settings->currency->code;

        return response()->json(
            [
                'success' => true,
                'driver_data' => $driverInformation,
                'user_data' => $userInfo,
                'settings' => $settings,

            ], 200);
    }


    //getDriverTrips
    public function getDriverTrips(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //get driver information
        $driverInformation = $this->driverInformationRepository->findByWhere(['driver_id' => $user_id])->first();
        //TBD
        // if(!$driverInformation)
        // {
        //     return response()->json(['error' => ['Driver information does not exist']], 422);
        // }
        $today = Carbon::now()->format('Y-m-d');

        //get driver trips
        $driverTrips = $this->plannedTripRepository->findByWhere(
            ['driver_id' => $user_id, 'planned_date' => $today],
            ['*'],
            ['plannedTripDetail.stop', 'bus', 'route', 'trip', 'reservations'])->sortBy('planned_date')->values()->all();

        //add isMorning flag
        foreach ($driverTrips as $key => $driverTrip) {
            if($driverTrip->route == null)
            {
                continue;
            }
            $driverTrip->is_morning = $driverTrip->route->is_morning;
            $driverTrip->reservations_count = count($driverTrip->reservations);
        }



        return response()->json(
            [
                'success' => true,
                'trips' => $driverTrips,
            ], 200);
    }

    //wallet payments
    public function getWalletPayments(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //check if user is a driver
        if($user->role != 2)
        {
            return response()->json(['error' => ['User is not a driver']], 422);
        }

        //get driver wallet payments
        $driverWalletPayments = $this->userPaymentRepository->findByWhere(['user_id' => $user_id]);

        //group by payment date, add payments in a day
        $driverWalletPayments = $driverWalletPayments->groupBy(function($item, $key) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        });

        //sum up the payments in a day
        $driverWalletPayments = $driverWalletPayments->map(function($item, $key) {
            $total = 0;
            foreach($item as $payment)
            {
                $total += $payment->amount;
            }
            return $total;
        });


        //get all redemptions of the driver
        $driverRedemptions = $this->redemptionRepository->findByWhere(['user_id' => $user_id]);
        // merge driverRedemptions with driverWalletPayments
        foreach($driverRedemptions as $redemption)
        {
            $driverWalletPayments->put(Carbon::parse($redemption->created_at)->format('Y-m-d'), -1*$redemption->redemption_amount);
        }

        //sort by date
        $driverWalletPayments = $driverWalletPayments->sortBy(function($item, $key) {
            return Carbon::parse($key);
        });



        $finalDriverWalletPayments = [];
        //convert to array of objects and name payment date as date and total as amount
        foreach($driverWalletPayments as $key => $value)
        {
            $finalDriverWalletPayments[] = [
                'payment_date' => $key,
                'amount' => $value,
            ];
        }


        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;
        $allow_ads_in_driver_app = $setting->allow_ads_in_driver_app;
        $allow_ads_in_parent_app = $setting->allow_ads_in_parent_app;
        $hide_schools = $setting->hide_schools;
        $distanceToDropOff = $setting->distance_to_drop_off;
        $distanceToPickup = $setting->distance_to_pickup;
        $distanceToSlowDown = $setting->distance_to_slow_down;
        $otp_required = $settings->otp_required;
        //hide_payment_parents
        $hide_payment_parents = $setting->hide_payment_parents;
        // Log::info($finalDriverWalletPayments);

        return response()->json([
            'success' => true,
            'payments' => $finalDriverWalletPayments,
            'wallet_balance' => $user->wallet,
            'currency_code' => $currency_code,
            'allow_ads_in_driver_app' => $allow_ads_in_driver_app,
            'allow_ads_in_parent_app' => $allow_ads_in_parent_app,
            'hide_schools' => $hide_schools,
            'hide_payment_parents' => $hide_payment_parents,
            'distance_to_drop_off' => $distanceToDropOff,
            'distance_to_pick_up' => $distanceToPickup,
            'distance_to_slow_down' => $distanceToSlowDown,
            'otp_required' => $otp_required,
        ], 200);
    }

    // add/edit bank account details
    public function addEditBankAccountDetails(Request $request, $user_id)
    {
        //validate the request
        $this->validate($request, [
            'bank_name' => 'required|string',
            'beneficiary_name' => 'required|string',
            'beneficiary_address' => 'required|string',
            'account_number' => 'required|string',

            'iban' => 'nullable|string',
            'swift' => 'nullable|string',
            'bic' => 'nullable|string',
            'routing_number' => 'nullable|string',
        ], [], []);

        $bankAccountData = [
            'bank_name' => $request->bank_name,
            'beneficiary_name' => $request->beneficiary_name,
            'beneficiary_address' => $request->beneficiary_address,
            'account_number' => $request->account_number,

            'iban' => $request->iban,
            'swift' => $request->swift,
            'bic' => $request->bic,
            'routing_number' => $request->routing_number,

            'user_id' => $user_id,
        ];

        Log::info($bankAccountData);

        //save bank account
        $bankAccount = $this->bankAccountRepository->create($bankAccountData);
        return $bankAccount;
    }

    //add/edit paypal account details
    public function addEditPaypalAccountDetails(Request $request, $user_id)
    {
        //validate the request
        $this->validate($request, [
            'email' => 'required|email',
        ], [], []);

        $paypalAccountData = [
            'email' => $request->email,
            'user_id' => $user_id,
        ];

        //save paypal account
        $paypalAccount = $this->paypalAccountRepository->create($paypalAccountData);

        return $paypalAccount;
    }

    //add/edit mobile money account details
    public function addEditMobileMoneyAccountDetails(Request $request, $user_id)
    {
        //validate the request
        $this->validate($request, [
            'phone_number' => 'required|string',
            'network' => 'required|string',
            'name' => 'string|nullable',
        ], [], []);

        $mobileMoneyAccountData = [
            'phone_number' => $request->phone_number,
            'network' => $request->network,
            'name' => $request->name,
            'user_id' => $user_id,
        ];

        Log::info($mobileMoneyAccountData);
        //save mobile money account
        $mobileMoneyAccount = $this->mobileMoneyAccountRepository->create($mobileMoneyAccountData);

        Log::info($mobileMoneyAccount);

        return $mobileMoneyAccount;
    }

    //takeAction on driver (approve, reject)
    public function takeAction(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'driver_id' => 'required|integer',
            'reason' => 'nullable|string', //required if action is reject
            'action' => 'required|integer',
        ], [], []);

        $driver_id = $request->driver_id;
        $school = Auth::user();
        $action = $request->action; //1 - approve, 2 - reject

        $driver = $this->userRepository->findByWhere(['id' => $driver_id, 'school_id' => $school->id], ['*'], ['driverInformation'])->first();
        if(!$driver)
        {
            return response()->json(['error' => ['Driver does not exist']], 422);
        }

        //check if driver is under review
        if($driver->status_id != 4)
        {
            return response()->json(['error' => ['Driver is not under review']], 422);
        }

        //check if action is valid
        if($action != 1 && $action != 2)
        {
            return response()->json(['error' => ['Invalid action']], 422);
        }

        //create transaction
        DB::beginTransaction();
        try {
            if($action == 1)
            {
                //approve driver
                $driver->status_id = 1;
            }
            else
            {
                //reject driver
                $driver->status_id = 2;
            }
            if($request->reason)
            {
                $driver->registration_response = $request->reason;
            }
            $driver->driverInformation->save();
            $driver->save();
            DB::commit();
            return response()->json(['success' => ['Action taken successfully']], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //updatePreferredPaymentMethod
    public function updatePreferredPaymentMethod(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //check if user is a driver
        if($user->role != 2)
        {
            return response()->json(['error' => ['User is not a driver']], 422);
        }


        //validate the request
        $this->validate($request, [
            'preferred_payment_method' => 'required',
        ], [], []);

        DB::beginTransaction();
        try {
            //cast to int
            $preferred_payment_method = (int)$request->preferred_payment_method;
            if($preferred_payment_method == 1)
            {
                //cash
                $user->redemption_preference = 1;
                $user->save();
            }
            else if ($preferred_payment_method == 2)
            {
                //bank account
                $this->addEditBankAccountDetails($request, $user_id);
                $user->redemption_preference = 2;
                $user->save();
            }
            else if ($preferred_payment_method == 3)
            {
                //paypal
                $this->addEditPaypalAccountDetails($request, $user_id);
                $user->redemption_preference = 3;
                $user->save();
            }
            else if ($preferred_payment_method == 4)
            {
                //mobile money
                $this->addEditMobileMoneyAccountDetails($request, $user_id);
                $user->redemption_preference = 4;
                $user->save();
            }
            else
            {
                //invalid preferred payment method
                return response()->json(['error' => ['Invalid preferred payment method']], 422);
            }
            //save
            DB::commit();

            $user = $this->userRepository->findById($user_id, ['*'], ['bankAccount', 'paypalAccount', 'mobileMoneyAccount']);

            return response()->json(['success' => ['Preferred payment method updated successfully'], 'user' => $user], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['errors' => ['Error'=> [$e->getMessage()]]], 422);
        }
    }

    //getPreferredPaymentMethod
    public function getPreferredPaymentMethod(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        //check if user is a driver
        if($user->role != 2)
        {
            return response()->json(['error' => ['User is not a driver']], 422);
        }

        $user = $this->userRepository->findById($user_id, ['*'], ['bankAccount', 'paypalAccount', 'mobileMoneyAccount']);

        return response()->json(['success' => ['Preferred payment method'], 'user' => $user], 200);
    }
}
