<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repository\UserRepositoryInterface;
use App\Repository\ChargeRepositoryInterface;
use App\Repository\UserPaymentRepositoryInterface;
use App\Repository\RedemptionRepositoryInterface;
use App\Repository\SettingRepositoryInterface;
use App\Repository\PlanRepositoryInterface;
use App\Repository\StudentGuardianRepositoryInterface;
use App\Repository\StudentSettingRepositoryInterface;
use App\Repository\BusRepositoryInterface;
use App\Repository\RouteStopRepositoryInterface;
use App\Repository\TripRepositoryInterface;
use App\Repository\NotificationRepositoryInterface;
use App\Repository\ReservationRepositoryInterface;
use DB;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Razorpay\Api\Api;
use App\Traits\UserUtils;
use EdwardMuss\Rave\Facades\Rave as Flutterwave;
use App\Models\FlutterwaveTransaction;
use App\Models\PaystackTransaction;
use App\Models\PaytabsTransaction;
use Paytabscom\Laravel_paytabs\Facades\paypage;
use App\Models\StudentSetting;
use App\Models\StudentTrip;
use App\Models\PlannedTrip;
use App\Models\SchoolSetting;

use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Mail;
use Excel;
use App\Imports\GuardianImportClass;
use Illuminate\Http\JsonResponse as JsonResponse;
use \File;
class UserController extends Controller
{
    private $RAZORPAY_MULTIPLE = 100;
    use UserUtils;
    //
    private $auth;
    private $userRepository;
    private $ChargeRepository;
    private $userPaymentRepository;
    private $redemptionRepository;
    private $settingRepository;
    private $planRepository;
    private $studentGuardianRepository;
    private $studentSettingRepository;
    private $busRepository;
    private $routeStopRepository;
    private $tripRepository;
    private $notificationRepository;
    private $messaging;
    private $reservationRepository;
    public function __construct(
        Auth $auth,
        UserRepositoryInterface $userRepository,
        ChargeRepositoryInterface $ChargeRepository,
        UserPaymentRepositoryInterface $userPaymentRepository,
        RedemptionRepositoryInterface $redemptionRepository,
        SettingRepositoryInterface $settingRepository,
        PlanRepositoryInterface $planRepository,
        StudentGuardianRepositoryInterface $studentGuardianRepository,
        StudentSettingRepositoryInterface $studentSettingRepository,
        RouteStopRepositoryInterface $routeStopRepository,
        TripRepositoryInterface $tripRepository,
        NotificationRepositoryInterface $notificationRepository,
        Messaging $messaging,
        ReservationRepositoryInterface $reservationRepository,
        BusRepositoryInterface $busRepository
    ) {
        $this->auth = $auth;
        $this->userRepository = $userRepository;
        $this->ChargeRepository = $ChargeRepository;
        $this->userPaymentRepository = $userPaymentRepository;
        $this->redemptionRepository = $redemptionRepository;
        $this->settingRepository = $settingRepository;
        $this->planRepository = $planRepository;
        $this->studentGuardianRepository = $studentGuardianRepository;
        $this->studentSettingRepository = $studentSettingRepository;
        $this->routeStopRepository = $routeStopRepository;
        $this->tripRepository = $tripRepository;
        $this->notificationRepository = $notificationRepository;
        $this->messaging = $messaging;
        $this->reservationRepository = $reservationRepository;
        $this->busRepository = $busRepository;
    }

    public function getAllSchools(Request $request)
    {
        $allSchools = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 2]]);

        return response()->json($allSchools, 200);
    }

    public function getAllStudents(Request $request)
    {
        $authUser = $request->user();
        $students = $this->userRepository->allWhere(['*'], ['studentGuardians.guardian', 'studentSettings.morningBus','studentSettings.afternoonBus'], [['role_id', '=', 6], ['school_id', '=', $authUser->id]]);
        //remove guardians except the parents
        $students = $students->map(function ($student) {
            $student->parent = $student->studentGuardians->map(function ($studentGuardian) {
                if($studentGuardian->guardian->role_id == 4)
                {
                    return $studentGuardian->guardian;
                }
            });
            return $student;
        });
        $students = $students->filter(function ($student) {
            return count($student->parent) > 0;
        });
        $students = $students->map(function ($student) {
            $student->parent = $student->parent[0];
            return $student;
        });
        //get values
        $students = $students->values();
        return response()->json($students, 200);
    }

    //getAllParentGuardians
    public function getAllParentGuardians(Request $request)
    {
        $authUser = $request->user();
        $guardians = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 5], ['parent_id', '=', $authUser->id]]);

        return response()->json(['guardians' => $guardians], 200);
    }

    //getAllGuardianStudents
    public function getAllGuardianStudents(Request $request)
    {
        $authUser = $request->user();
        $guardian = $this->userRepository->findById($authUser->id, ['*'], ['guardianStudents.student', 'studentSchool']);
        //add school to each student
        $guardian->guardianStudents->map(function ($studentGuardian) {
            $studentGuardian->student->school = $studentGuardian->student->studentSchool;
            return $studentGuardian;
        });
        $students = $guardian->guardianStudents->map(function ($studentGuardian) {
            return $studentGuardian->student;
        });

        //loop through the students and get the getPickupAndDropOffTimes for each student
        $students->map(function ($student) use ($guardian) {
            $student->student_details = $this->getStudentSettings($student->id);
            return $student;
        });

        return response()->json(['students' => $students], 200);
    }

    private function getAllSchoolGuardians($school_id)
    {
        $school = $this->userRepository->findByWhere([['id', '=', $school_id]], ['*'], ['schoolStudents.studentGuardians.guardian'])->first();

        //get all students of the school
        $students = $school->schoolStudents;
        // get all guardians of the students
        $guardians = $students->map(function ($student) {
            return $student->studentGuardians->map(function ($studentGuardian) {
                return $studentGuardian->guardian;
            });
        });
        $guardians = $guardians->flatten();
        //remove duplicates
        $guardians = $guardians->unique('id');
        $guardians = $guardians->values();
        return $guardians;
    }

    //getAllGuardians
    public function getAllGuardians(Request $request)
    {
        $authUser = $request->user();
        //guardians
        $guardians = $this->getAllSchoolGuardians($authUser->id);
        return response()->json($guardians, 200);
    }


    //getAllParents
    public function getAllParents(Request $request)
    {
        $authUser = $request->user();
        //guardians
        $guardians = $this->getAllSchoolGuardians($authUser->id);
        //get only the parents
        $parents = $guardians->filter(function ($guardian) {
            return $guardian->role_id == 4;
        });

        $parents = $parents->values();

        return response()->json(['parents' => $parents], 200);
    }

    //getAllDrivers
    public function getAllDrivers(Request $request)
    {
        $authUser = $request->user();
        //driver_schools
        $drivers = $this->userRepository->allWhere(['*'], ['bus', 'driverInformation.documents'], [['school_id', '=', $authUser->id], ['role_id', '=', 3]], true);

        //loop through the drivers and check the expiry date of the documents
        foreach ($drivers as $driver) {
            $expired = false;
            foreach ($driver?->driverInformation?->documents as $document) {
                if($document->expiry_date < Carbon::now())
                {
                    $expired = true;
                    break;
                }
            }
            $driver->expired = $expired;
        }

        //remove the documents
        $drivers = $drivers->map(function ($driver) {
            unset($driver->driverInformation->documents);
            return $driver;
        });

        return response()->json($drivers, 200);
    }

    //getAllStudentsOfSchool
    public function getAllStudentsOfSchool($school_id)
    {
        $students = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 6], ['school_id', '=', $school_id]]);
        return $students;
    }
    //getAllParentsOfSchool
    private function getAllParentsOfSchool($school_id)
    {
        // get all students of the school
        $students = $this->getAllStudentsOfSchool($school_id);
        // get all parents of the students
        $parents = $students->map(function ($student) {
            return $student->parent;
        });
    }

    private function canView($authUser, $user_id)
    {
        $user = $this->userRepository->findById($user_id);
        $canView = false;
        //admin can view all users
        if($authUser->role_id == 1)
        {
            $canView = true;
        }
        //school can view only its parents, drivers, students, and guardians
        else if($authUser->role_id == 2)
        {
            $school = $this->userRepository->findByWhere([['id', '=', $authUser->id]], ['*'], ['schoolStudents', 'schoolDrivers'])->first();
            if($user->role_id == 2)
            {
                //check if the user is the school
                $canView = $authUser->id == $user->id;
            }
            if($user->role_id == 3) //driver
            {
                //check if the driver in schoolDrivers
                $canView = $school->schoolDrivers->contains('id', $user->id);
            }
            else if($user->role_id == 6) //student
            {
                //check if the student in schoolStudents
                $canView = $school->schoolStudents->contains('id', $user->id);
            }
            else if($user->role_id == 5 || $user->role_id == 4) //guardian or parent
            {
                //check if the guardian in schoolGuardians
                $guardians = $this->getAllSchoolGuardians($authUser->id);
                $guardian_ids = $guardians->map(function ($guardian) {
                    return $guardian->id;
                });

                $canView = $guardian_ids->contains($user->id);
            }
        }
        //parents can view only their students and guardians
        else if($authUser->role_id == 4 || $authUser->role_id == 5)
        {
            //get student guardians
            $guardians = $this->studentGuardianRepository->allWhere(['*'], [], [['guardian_id', '=', $authUser->id], ['student_id', '=', $user->id]]);
            $guardian_ids = $guardians->map(function ($guardian) {
                return $guardian->guardian_id;
            });
            //check if guardians
            $canView = count($guardian_ids) > 0;
        }
        else {
            //check if the user is the authUser
            $canView = $authUser->id == $user->id;
        }
        return $canView;
    }

    public function getUser(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'user_id' => 'required|integer',
        ], [], []);
        $user_id = $request->user_id;
        $authUser = $request->user();
        //get the current currency
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        $canView = $this->canView($authUser, $user_id);
        if(!$canView)
        {
            return response()->json(['message' => 'You are not allowed to view this user'], 403);
        }
        $user = $this->userRepository->findByWhere([['id', '=', $user_id]], ['*'], ['schoolStudents', 'schoolRoutes', 'schoolDrivers', 'schoolBuses', 'schoolTrips', 'schoolStops', 'guardianStudents.student', 'driverInformation.documents'])->first();
        if($user->role_id == 2) //school
        {
            //count students, routes, drivers, buses, trips, stops
            $user->student_count = $user->schoolStudents->count();
            $user->route_count = $user->schoolRoutes->count();
            $user->driver_count = $user->schoolDrivers->count();
            $user->bus_count = $user->schoolBuses->count();
            $user->trip_count = $user->schoolTrips->count();
            $user->stop_count = $user->schoolStops->count();
            // $user->currency = $currency_code;
        }
        // else if($user->role_id == 3) //driver
        // {
        //     // get 'driverInformation.documents'
        //     $user->driverInformation->documents;
        // }
        else if($user->role_id == 4 || $user->role_id == 5) //parent or guardian
        {
            // count students
            $students = $user->guardianStudents;
            //map
            $students = $students->map(function ($studentGuardian) {
                return $studentGuardian->student;
            });
            $user->students = $students;

        }
        else if($user->role_id == 6)
        {
            //get the student
            $student = $user;
            //get the student's guardians
            $guardians = $student->studentGuardians;
            //map
            $guardians = $guardians->map(function ($studentGuardian) {
                return $studentGuardian->guardian;
            });
            $student->guardians = $guardians;
            //add student settings
            $student->student_details = $this->getStudentSettings($student->id);
            $user = $student;
        }

        return response()->json($user, 200);
    }

    //takeAction on driver/student (approve, reject)
    public function takeAction(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'user_id' => 'required|integer',
            'reason' => 'nullable|string', //required if action is reject
            'action' => 'required|integer',
        ], [], []);

        $user_id = $request->user_id;
        $action = $request->action; //1 - approve, 2 - reject

        $authUser = $request->user();
        $canView = $this->canView($authUser, $user_id);
        if(!$canView)
        {
            return response()->json(['message' => 'You are not allowed to edit this user'], 403);
        }

        $user = $this->userRepository->findById($user_id, ['*'], ['driverInformation']);

        if(!$user)
        {
            return response()->json(['error' => ['User does not exist']], 422);
        }

        //check if user is driver or student
        if($user->role_id != 3 && $user->role_id != 6)
        {
            return response()->json(['error' => ['User is not a driver or student']], 422);
        }

        //check if user is under review
        // if($user->status_id != 4)
        // {
        //     return response()->json(['error' => ['User is not under review']], 422);
        // }

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
                //approve user
                $user->status_id = 1;
            }
            else
            {
                //reject user
                $user->status_id = 2;
            }
            if($request->reason)
            {
                $user->registration_response = $request->reason;
            }
            if($user->driverInformation)
            {
                $user->driverInformation->save();
            }
            $actionText = "";
            if($action == 1)
            {
                $actionText = "Your registration for " . $user->name . " has been approved";
            }
            else
            {
                $actionText = "Your registration for " . $user->name . " has been rejected because " . $request->reason;
            }
            $this->sendNotificationToUser($user, $actionText);
            $user->save();
            DB::commit();
            return response()->json(['success' => ['Action taken successfully']], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
    public function suspendActivate(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'user_id' => 'required|integer',
        ], [], []);

        $user_id = $request->user_id;
        $authUser = $request->user();
        $canView = $this->canView($authUser, $user_id);
        if(!$canView)
        {
            return response()->json(['message' => 'You are not allowed to edit this user'], 403);
        }

        $user = $this->userRepository->findById($user_id);
        $user->status_id = $user->status_id != 1 ? 1 : 3;
        $this->userRepository->update($user_id, $user->toArray());
        return response()->json(['success' => ['user updated successfully']]);
    }

    public function upload_user_photo(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'avatar' => 'required',
        ], [], []);

        $user = $request->user();
        $user_id = $user->id;
        //get user
        $user = $this->userRepository->findById($user_id);
        // check if image has been received from form
        if ($request->file('avatar')) {
            Log::info('file');
            $imageName = time().'.'.$request->avatar->getClientOriginalExtension();
            $storagePath = Storage::url('avatars/'. $user_id);
            $imageAbsolutePath = public_path('/backend'.$storagePath);
            $request->avatar->move($imageAbsolutePath, $imageName);

            // Update user's avatar column on 'users' table
            $user->avatar = $storagePath .'/' . $imageName;

            if ($user->save()) {
                return response()->json([
                    'status'    =>  'success',
                    'message'   =>  'Profile Photo Updated!',
                    'avatar_url' =>  $user->avatar
                ]);
            } else {
                return response()->json([
                    'status'    => 'failure',
                    'message'   => 'failed to update profile photo!',
                    'avatar_url' => NULL
                ], 400);
            }
        }
        else
        {
            $image = $request->avatar;
            try{
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $image = base64_decode($image);

                $imageName = time().'.png';
                $storagePath = Storage::url('avatars/'. $user_id);
                $imageAbsolutePath = public_path('/backend'.$storagePath);
                file_put_contents($imageAbsolutePath.'/'.$imageName, $image);

                // Update user's avatar column on 'users' table
                $user->avatar = $storagePath .'/' . $imageName;
                $user->save();

                return response()->json([
                    'status'    =>  'success',
                    'message'   =>  'Profile Photo Updated!',
                    'avatar_url' =>  $user->avatar
                ]);
            }
            catch(\Exception $e)
            {
                Log::info($e->getMessage());
                return response()->json([
                    'status'    => 'failure',
                    'errors'   => 'No image file uploaded!',
                    'avatar_url' => NULL
                ], 400);
            }
        }
    }


    public function changePassword(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'user_id' => 'required|integer',
            'password' => 'required',
        ], [], []);
        $user_id = $request->user_id;
        //get user
        $user = $this->userRepository->findById($user_id);

        //check if uid is set
        if($user->uid == null)
        {
            return response()->json(['message' => 'User does not have a password'], 403);
        }

        $password = $request->password;

        //update the password in auth
        $this->auth->changeUserPassword($user->uid, $password);
        return response()->json(['success' => ['user updated successfully']]);
    }


    public function Edit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'user' => 'required',
            'user.id' => 'integer|required',
            'user.name' => 'required|string',
            'user.email' => 'required|email',
            'user.tel_number' => 'numeric|nullable',
            'user.balance' => 'numeric|nullable',
            'user.student_identification' => 'string|nullable',
            'user.status_id' => ['nullable', Rule::in([1, 2, 3])], //1, active, 2 pending, 3 suspended
        ], [], []);

        //get user
        $user_id = $request->user['id'];
        $authUser = $request->user();
        $canView = $this->canView($authUser, $user_id);
        if(!$canView)
        {
            return response()->json(['message' => 'You are not allowed to edit this user'], 403);
        }
        $user = $this->userRepository->findById($user_id);
        $user->name = $request->user['name'];
        $user->email = $request->user['email'];
        if(array_key_exists('tel_number', $request->user))
        {
            $user->tel_number = $request->user['tel_number'];
        }
        if (array_key_exists('student_identification', $request->user)) {
            $user->student_identification = $request->user['student_identification'];
        }
        DB::beginTransaction();
        try {

            //if admin, he can update the balance
            if($authUser->role_id == 1)
            {
                if(array_key_exists('balance', $request->user))
                {
                    $user->balance = $request->user['balance'];
                }
            }

            if($user->role_id == 1 || $user->role_id == 2)
            {
                if(array_key_exists('status_id', $request->user))
                {
                    $user->status_id = $request->user['status_id'];
                }
            }

            if($user->role_id != 6)
            {
                //update the user in firebase
                $this->auth->updateUser($user->uid, [
                    'displayName' => $user->name,
                    'email' => $user->email,
                ]);
            }

            $this->userRepository->update($user_id, $user->toArray());
            // if($user->role_id == 1 || $user->role_id == 2)  //update the admin user
            // {
            //     //only allowed changes are balance, name, email, tel_number for admins
            //     $this->userRepository->update($user_id, $user->toArray());
            // }
            // else{
            //     //can update status
            //     if(array_key_exists('status_id', $request->user))
            //     {
            //         $user->status_id = $request->user['status_id'];
            //     }
            //     $this->userRepository->update($user_id, $user->toArray());
            // }
            DB::commit();
            return response()->json(['success' => ['user updated successfully']]);
        }
        catch (UserNotFound $e) {
            DB::rollback();
            return response()->json(['message' => 'Firebase: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
            DB::rollback();
            return response()->json(['message' => 'User update failed'], 500);
        }
    }

    public function getDevices(Request $request)
    {
        Log::info('request: getDevices11');
        // get the required user
        $user = $request->user();
        Log::info('user: '.$user);
        //if the user found
        if ($user) {
            return response()->json(['devices' => $user->tokens()->select('id', 'name', 'last_used_at')->get()]);
        }
        else
        {
            return response()->json(['errors' => ['User' => ['user does not exist']]], 403);
        }
    }

    private function validatePlan($plan_id, $user)
    {
        $plan = $this->planRepository->findById($plan_id);
        if(!$plan)
        {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        $is_school = $user->role_id == 2;
        if($is_school && $plan->plan_type != 0)
        {
            return response()->json(['message' => 'You cannot subscribe to this plan'], 422);
        }
        if(!$is_school && $plan->plan_type != 1)
        {
            return response()->json(['message' => 'You cannot subscribe to this plan'], 422);
        }

        return $plan;
    }

    //initializeFlutterwaveOrder
    public function initializeFlutterwaveOrder(Request $request)
    {
        //validate the request amount
        $this->validate($request, [
            'plan_id' => 'required|integer',
        ], [], []);
        $plan_id = $request->plan_id;
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        $plan = $this->validatePlan($plan_id, $user);

        $order_amount = $plan->price;

        //get the currency code
        $setting = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $setting->currency->code;

        $publicKey = env('FLW_PUBLIC_KEY');
        $email = $user->email;
        return response()->json(['order_amount' => $order_amount, 'currency' => $currency_code, 'key' => $publicKey, 'email' => $email]);
    }

    //createRazorpayOrder
    public function createRazorpayOrder(Request $request)
    {
        //validate the request amount
        $this->validate($request, [
            'plan_id' => 'required|integer',
        ], [], []);
        $plan_id = $request->plan_id;
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }

        $plan = $this->validatePlan($plan_id, $user);

        $amount = $plan->price * $this->RAZORPAY_MULTIPLE;

        //get the currency code
        $setting = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $setting->currency->code;

        $api_key = env('RAZORPAY_KEY');
        $api_secret = env('RAZORPAY_SECRET');
        $razorPayApi = new Api($api_key, $api_secret);

        //create the order
        $order = $razorPayApi->order->create(array('amount' => $amount, 'currency' => $currency_code));
        $order_id = $order->id;
        $order_amount = $order->amount;
        return response()->json(['order_id' => $order_id, 'order_amount' => $order_amount, 'currency' => $currency_code, 'key' => $api_key]);
    }

    public function finalizePayment($plan, $user)
    {
        $is_school = $user->role_id == 2;
        $amount = $plan->price;
        $coins = $plan->coin_count;

        if($plan->availability == 1)
        {
            $available = $this->checkAvailability($user, $plan);
            if(!$available)
            {
                return response()->json(['message' => 'You have already subscribed to this plan'], 422);
            }
        }
        //add user charge
        $userCharge = [
            'price' => $amount,
            'coin_count' => $coins,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'payment_date' => Carbon::now(),
            // 'payment_method' => $paymentMethod
        ];
        if($is_school)
        {
            $userCharge['school_id'] = $user->id;
        }
        else
        {
            $userCharge['parent_id'] = $user->id;
        }
        $this->ChargeRepository->create($userCharge);

        //add balance
        $user->balance += $coins;
        $this->userRepository->update($user->id, $user->toArray());
        if($is_school)
        {
            $payments = $this->ChargeRepository->allWhere(['*'], [], [['school_id', '=', $user->id]], true);
        }
        else
        {
            $payments = $this->ChargeRepository->allWhere(['*'], [], [['parent_id', '=', $user->id]], true);
        }
        foreach ($payments as $payment) {
            $payment->payment_method = $payment->payment_method == 1 ? 'card':'paypal';
        }

        //get the current currency
        $setting = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $setting->currency->code;
        $user = $this->userRepository->allWhere(['*'], ['schoolCharges', 'parentCharges'], [['id', '=', $user->id]], true)->first();

        return ['payments' => $payments, 'wallet_balance' => $user->balance, 'currency' => $currency_code, 'user' => $user];
    }

    //captureRazorpayPayment
    public function captureRazorpayPayment(Request $request)
    {
        //validate the request paymentId
        $this->validate($request, [
            'plan_id' => 'required|integer',
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'string',
            'razorpay_signature' => 'string',
        ], [], []);

        $paymentId = $request->razorpay_payment_id;
        $api_key = env('RAZORPAY_KEY');
        $api_secret = env('RAZORPAY_SECRET');
        $razorPayApi = new Api($api_key, $api_secret);
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        //fetch the payment
        $payment = $razorPayApi->payment->fetch($paymentId);
        if ($payment) {
            DB::beginTransaction();
            try {
                $amount = $payment->amount;
                //get the plan
                $plan_id = $request->plan_id;
                $plan = $this->validatePlan($plan_id, $user);
                //check the plan price and the amount
                if($plan->price * $this->RAZORPAY_MULTIPLE != $amount)
                {
                    return response()->json(['message' => 'Invalid amount'], 422);
                }

                if($request->razorpay_order_id)
                {
                    try
                    {
                        //check the signature
                        $razorPayApi->utility->verifyPaymentSignature([
                            'razorpay_payment_id' => $paymentId,
                            'razorpay_order_id' => $request->razorpay_order_id,
                            'razorpay_signature' => $request->razorpay_signature
                        ]);
                        $success = true;
                    }
                    catch(SignatureVerificationError $e)
                    {
                        $success = false;
                        $error = 'Razorpay Error : ' . $e->getMessage();
                        Log::info($error);
                    }
                    if (!$success) {
                        return response()->json(['message' => 'Payment not verified'], 422);
                    }
                }
                else
                {
                    $paymentResponse = $payment->capture(array('amount' => $amount));
                    Log::info('paymentResponse: '. json_encode($paymentResponse));
                    //check if paymentResponse contains error
                    if(isset($paymentResponse->error))
                    {
                        Log::info('paymentResponse: '.json_encode($paymentResponse));
                        return response()->json(['message' => $paymentResponse->error->description], 422);
                    }
                }
                $finalizedPayment = $this->finalizePayment($plan, $user);
                //['payments' => $payments, 'wallet_balance' => $user->balance, 'currency' => $currency_code, 'user' => $user];
                $payments = $finalizedPayment['payments'];
                $wallet_balance = $finalizedPayment['wallet_balance'];
                $currency_code = $finalizedPayment['currency'];
                DB::commit();

                $plans = $this->getAvailablePlans($user);
                return response()->json([
                    'success' => true,
                    'plans' => $plans,
                    'payments' => $payments,
                    'wallet_balance' => $wallet_balance,
                    'currency' => $currency_code,
                    // 'payment_method' => $paymentMethod
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        else
        {
            return response()->json(['message' => 'Payment not found'], 404);
        }
    }

    //captureFlutterwavePayment
    public function captureFlutterwavePayment(Request $request)
    {
        //validate the request transactionId
        $this->validate($request, [
            'transaction_id' => 'required',
            'plan_id' => 'required|integer',
        ], [], []);

        $transactionId = $request->transaction_id;
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        //check if the transactionId is in the flutterwave_transactions table
        $flutterwaveTransaction = FlutterwaveTransaction::where('transaction_id', $transactionId)->first();
        if($flutterwaveTransaction != null)
        {
            return response()->json(['message' => 'Payment already captured'], 422);
        }
        //fetch the payment
        $verificationResponse = Flutterwave::verifyTransaction($transactionId);
        if ($verificationResponse) {
            Log::info('verificationResponse: '. json_encode($verificationResponse));
            DB::beginTransaction();
            try {
                //store the transactionId in the flutterwave_transactions table
                $flutterwaveTransaction = new FlutterwaveTransaction();
                $flutterwaveTransaction->transaction_id = $transactionId;
                $flutterwaveTransaction->save();
                // get charged_amount from the verification response
                $amount = $verificationResponse["data"]["charged_amount"];


                $plan = $this->planRepository->findById($request->plan_id);
                if(!$plan)
                {
                    return response()->json(['message' => 'Plan not found'], 404);
                }

                if($plan->price != $amount)
                {
                    return response()->json(['message' => 'Invalid amount'], 422);
                }

                $finalizedPayment = $this->finalizePayment($plan, $user);

                $payments = $finalizedPayment['payments'];
                $wallet_balance = $finalizedPayment['wallet_balance'];
                $currency_code = $finalizedPayment['currency'];

                DB::commit();

                $plans = $this->getAvailablePlans($user);
                return response()->json([
                    'success' => true,
                    'plans' => $plans,
                    'payments' => $payments,
                    'wallet_balance' => $wallet_balance,
                    'currency' => $currency_code,
                    // 'payment_method' => $paymentMethod
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        else
        {
            return response()->json(['message' => 'Payment not found'], 404);
        }
    }

    //capturePaytabsPayment
    public function capturePaytabsPayment(Request $request)
    {
        //validate the request paymentId
        $this->validate($request, [
            'tran_ref' => 'required|string',
        ], [], []);

        $tranRef = $request->tran_ref;
        $user = $request->user();

        //check if the transactionId is in the flutterwave_transactions table
        $paytabsTransaction = PaytabsTransaction::where('transaction_id', $tranRef)->first();
        if($paytabsTransaction != null)
        {
            return response()->json(['message' => 'Payment already captured'], 422);
        }
        //fetch the transaction
        $transaction = Paypage::queryTransaction($tranRef);
        Log::info('transaction: '. json_encode($transaction));

        //check if the transaction is found
        if ($transaction == null) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        //check if the transaction is sale (lower case)
        if(strtolower($transaction->tran_type) != 'sale')
        {
            return response()->json(['message' => 'Payment not in sale status. It has ' . $transaction->tran_type . " status"], 422);
        }

        if ($transaction) {
            DB::beginTransaction();
            try {

                //store the transactionId in the PaytabsTransaction table
                $paytabsTransaction = new PaytabsTransaction();
                $paytabsTransaction->transaction_id = $tranRef;
                $paytabsTransaction->save();

                $amount = $transaction->tran_total;
                $paymentMethod = 1;
                //add user charge
                $userCharge = [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'payment_date' => Carbon::now(),
                    'payment_method' => $paymentMethod
                ];
                $this->ChargeRepository->create($userCharge);

                //add balance
                $user->balance += $amount;
                $this->userRepository->update($user->id, $user->toArray());

                $payments = $this->ChargeRepository->allWhere(['*'], [], [['user_id', '=', $user->id]], true);
                foreach ($payments as $payment) {
                    $payment->payment_method = $payment->payment_method == 1 ? 'card':'paypal';
                }
                DB::commit();

                $paymentMethod = $this->getPaymentMethod();
                //get the current currency
                $setting = $this->settingRepository->all(['*'], ['currency'])->first();
                $currency_code = $setting->currency->code;

                return response()->json([
                    'success' => true,
                    'payments' => $payments,
                    'wallet_balance' => $user->balance,
                    'currency' => $currency_code,
                    'payment_method' => $paymentMethod
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        else
        {
            return response()->json(['message' => 'Payment not found'], 404);
        }
    }

    //capturePaystackPayment
    public function capturePaystackPayment(Request $request)
    {
        //validate the request paymentId
        $this->validate($request, [
            'reference' => 'required|string',
        ], [], []);

        $reference = $request->reference;

        //check if the transactionId is in the PaystackTransaction table
        $paystackTransaction = PaystackTransaction::where('transaction_reference', $reference)->first();
        if($paystackTransaction != null)
        {
            return response()->json(['message' => 'Payment already captured'], 422);
        }

        $secretKey = env('PAYSTACK_SECRET_KEY');
        $paystack = new \Yabacon\Paystack($secretKey);
        try
        {
            // verify using the library
            $tranx = $paystack->transaction->verify([
                'reference'=>$reference]);
                Log::info('tranx: '. json_encode($tranx));
            if ($tranx) {
                DB::beginTransaction();
                try {
                    //get metadata
                    $metadata = $tranx->data->metadata;
                    //get plan_id
                    $plan_id = $metadata->plan_id;
                    $user = $request->user();
                    if($user == null)
                    {
                        //validate the request with parent_id
                        $this->validate($request, [
                            'parent_id' => 'required|integer',
                        ], [], []);
                        $parent_id = $request->parent_id;
                        $user = $this->userRepository->findById($parent_id);
                        if(!$user)
                        {
                            return response()->json(['message' => 'User not found'], 404);
                        }
                    }
                    $plan = $this->validatePlan($plan_id, $user);
                    if(!$plan)
                    {
                        return response()->json(['message' => 'Plan not found'], 404);
                    }
                    $amount = $plan->price;
                    //get the amount
                    $paidAmount = $tranx->data->amount / 100;
                    if($amount != $paidAmount)
                    {
                        return response()->json(['message' => 'Invalid amount'], 422);
                    }


                    $finalizedPayment = $this->finalizePayment($plan, $user);

                    $payments = $finalizedPayment['payments'];
                    $wallet_balance = $finalizedPayment['wallet_balance'];
                    $currency_code = $finalizedPayment['currency'];

                    //store the transactionId in the PaystackTransaction table
                    $paystackTransaction = new PaystackTransaction();
                    $paystackTransaction->transaction_reference = $reference;
                    $paystackTransaction->save();

                    DB::commit();

                    $plans = $this->getAvailablePlans($user);
                    return response()->json([
                        'success' => true,
                        'plans' => $plans,
                        'payments' => $payments,
                        'wallet_balance' => $wallet_balance,
                        'currency' => $currency_code,
                        // 'payment_method' => $paymentMethod
                    ], 200);
                } catch (\Exception $e) {
                    DB::rollback();
                    Log::info($e->getMessage());
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
            else
            {
                return response()->json(['message' => 'Payment not found'], 404);
            }
        } catch(\Yabacon\Paystack\Exception\ApiException $e){
            Log::info($e->getResponseObject());
        }
    }

    //initializeStripePayment
    public function initializeStripePayment(Request $request)
    {
        //validate the request amount
        $this->validate($request, [
            'plan_id' => 'required|integer',
        ], [], []);
        $plan_id = $request->plan_id;
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        $plan = $this->validatePlan($plan_id, $user);

        $amount = $plan->price * 100;

        //get the currency code
        $setting = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $setting->currency->code;

        $publicKey = env('STRIPE_KEY');
        $secretKey = env('STRIPE_SECRET');
        $stripe = new \Stripe\StripeClient(
            $secretKey
        );
        //create payment intent
        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $amount,
            'currency' => $currency_code,
            'payment_method_types' => ['card'],
        ]);

        return response()->json(['plan' => $plan, 'currency' => $currency_code, 'key' => $publicKey, 'payment_intent' => $paymentIntent->client_secret, 'payment_intent_id' => $paymentIntent->id,
        'name' => $user->name, 'email' => $user->email]);
    }

    //captureStripePayment
    public function captureStripePayment(Request $request)
    {
        //validate the request paymentId
        $this->validate($request, [
            'plan_id' => 'required|integer',
            'payment_intent' => 'required',
        ], [], []);

        $paymentIntent = $request->payment_intent;
        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        $secretKey = env('STRIPE_SECRET');
        $stripe = new \Stripe\StripeClient(
            $secretKey
        );
        $paymentIntent = $stripe->paymentIntents->retrieve(
            $paymentIntent
        );
        Log::info('paymentIntent: '. json_encode($paymentIntent));
        if ($paymentIntent) {
            DB::beginTransaction();
            try {
                //check if payment is successful
                if($paymentIntent->status != 'succeeded')
                {
                    return response()->json(['message' => 'Payment not successful'], 422);
                }

                $amount = $paymentIntent->amount_received;
                //get the plan
                $plan = $this->validatePlan($request->plan_id, $user);
                //check the plan price and the amount
                if($plan->price * 100 != $amount)
                {
                    return response()->json(['message' => 'Invalid amount'], 422);
                }

                $finalizedPayment = $this->finalizePayment($plan, $user);
                //['payments' => $payments, 'wallet_balance' => $user->balance, 'currency' => $currency_code, 'user' => $user];
                $payments = $finalizedPayment['payments'];
                $wallet_balance = $finalizedPayment['wallet_balance'];
                $currency_code = $finalizedPayment['currency'];
                DB::commit();

                $plans = $this->getAvailablePlans($user);
                return response()->json([
                    'success' => true,
                    'plans' => $plans,
                    'payments' => $payments,
                    'wallet_balance' => $wallet_balance,
                    'currency' => $currency_code,
                    // 'payment_method' => $paymentMethod
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
                Log::info($e->getMessage());
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        else
        {
            return response()->json(['message' => 'Payment not found'], 404);
        }
    }


    private function checkAvailability($user, $plan)
    {
        $is_school = $user->role_id == 2;
        $charges = $is_school ? $user->schoolCharges : $user->parentCharges;
        $available = true;
        foreach ($charges as $charge) {
            if($charge->plan_id == $plan->id)
            {
                $available = false;
                break;
            }
        }
        return $available;
    }
    //captureBraintree
    public function captureBraintree(Request $request)
    {
        //validate the request
        $validator = $this->validate($request, [
            'nonce' => 'required|string',
            'plan_id' => 'required|integer',
        ], [] , []);

        $user = $request->user();
        if($user == null)
        {
            //validate the request with parent_id
            $this->validate($request, [
                'parent_id' => 'required|integer',
            ], [], []);
            $parent_id = $request->parent_id;
            $user = $this->userRepository->findById($parent_id);
            if(!$user)
            {
                return response()->json(['message' => 'User not found'], 404);
            }
        }
        $is_school = $user->role_id == 2;
        $nonce = $request->nonce;
        $plan_id = $request->plan_id;
        $plan = $this->planRepository->findById($plan_id);
        if($is_school && $plan->plan_type != 0)
        {
            return response()->json(['message' => 'You cannot subscribe to this plan'], 422);
        }
        if(!$is_school && $plan->plan_type != 1)
        {
            return response()->json(['message' => 'You cannot subscribe to this plan'], 422);
        }
        $amount = $plan->price;
        $coins = $plan->coin_count;

        if($plan->availability == 1)
        {
            $available = $this->checkAvailability($user, $plan);
            if(!$available)
            {
                return response()->json(['message' => 'You have already subscribed to this plan'], 422);
            }
        }

        $gateway = new \Braintree\Gateway([
            'environment' => env('BRAINTREE_ENV'),
            'merchantId' => env('BRAINTREE_MERCHANT_ID'),
            'publicKey' => env('BRAINTREE_PUBLIC_KEY'),
            'privateKey' => env('BRAINTREE_PRIVATE_KEY')
            ]);

        Log::info('nonce = '.$nonce . ' amount = '.$amount);
        //
        DB::beginTransaction();
        try {
            if($plan->price != 0)
            {
                $paymentMethodNonce = $gateway->paymentMethodNonce()->find($nonce);
                Log::info(json_encode($paymentMethodNonce));

                $status =  $gateway->transaction()->sale(
                    [
                        'amount' => $amount,
                        'paymentMethodNonce' => $nonce,
                        'options' => [
                            'submitForSettlement' => True
                        ]
                    ]
                );
                //check $status
                if (!$status->success) {
                    Log::info('status: '.$status);
                    Log::info('status: '.$status->message);
                    return response()->json(['message' => $status->message], 422);
                }
                Log::info($paymentMethodNonce->type);
                $paymentMethod = 1;
                // check if the payment method contains the word 'PayPal'
                if(str_contains($paymentMethodNonce->type, 'PayPal')) {
                    $paymentMethod = 2;
                }
            }

            $finalizedPayment = $this->finalizePayment($plan, $user);
            //['payments' => $payments, 'wallet_balance' => $user->balance, 'currency' => $currency_code, 'user' => $user];
            $payments = $finalizedPayment['payments'];
            $wallet_balance = $finalizedPayment['wallet_balance'];
            $currency_code = $finalizedPayment['currency'];

            DB::commit();

            $plans = $this->getAvailablePlans($user);
            return response()->json([
                'success' => true,
                'plans' => $plans,
                'payments' => $payments,
                'wallet_balance' => $wallet_balance,
                'currency' => $currency_code,
                // 'payment_method' => $paymentMethod
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json($status);
    }
    //payments
    public function getWalletCharges(Request $request)
    {
        Log::info('request: getWalletCharges');
        // get the required user
        $user = $request->user();
        //if the user found
        if ($user) {
            if($user->role_id == 5)
            {
                $parentId = $user->parent_id;
                //get the parent
                $user = $this->userRepository->findById($parentId);
            }
            $payments = $user->parentCharges()->get();
            foreach ($payments as $payment) {
                $payment->payment_method = $payment->payment_method == 1 ? 'card':'paypal';
            }
            $paymentMethod = $this->getPaymentMethod();
            //get the current currency
            $setting = $this->settingRepository->all(['*'], ['currency'])->first();
            $currency_code = $setting->currency->code;
            //get all available plans
            $plans = $this->getAvailablePlans($user);

            Log::info('payments currency_code: '.$currency_code);
            return response()->json(['success' => true,
                'payments' => $payments,
                'wallet_balance' => $user->balance,
                'currency' => $currency_code,
                'payment_method' => $paymentMethod,
                'plans' => $plans
            ], 200);
        }
        else
        {
            return response()->json(['errors' => ['User' => ['user does not exist']]], 403);
        }
    }

    //requestCoins
    public function requestCoins(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'plan_id' => 'required|integer',
        ], [], []);

        $plan_id = $request->plan_id;
        $user = $request->user();
        $plan = $this->planRepository->findById($plan_id);
        if(!$plan)
        {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        $amount = $plan->price;
        $coins = $plan->coin_count;
        Log::info('amount: '.$amount . ' coins: '.$coins . ' plan: '.$plan->name);

        if($amount == 0)
        {
            $request->merge(['nonce' => '123']);
            return $this->captureBraintree($request);
        }

        //get the plan
        $plan = $this->validatePlan($plan_id, $user);

        //check if the return is an error
        if($plan instanceof JsonResponse)
        {
            return $plan;
        }

        if($plan->availability == 1)
        {
            $available = $this->checkAvailability($user, $plan);
            if(!$available)
            {
                return response()->json(['message' => 'You have already subscribed to this plan'], 422);
            }
        }

        //for a link to pay as pay-parent-plan/plan=5&parent=3
        $paymentLink = env('APP_URL') . '/pay-parent-plan/plan=' . $plan_id . '&parent=' . $user->id;

        //send the payment link to the parent via email
        $subject = 'Request for coins';
        // $message = 'Dear ' . $name . ', <br> You have requested for ' . $coins . ' coins. Please click on the link below to make payment <br> <a href="' . $paymentLink . '">Pay Now</a>';

        //send email to the parent with the link
        $parentEmail = $user->email;
        $parentName = $user->name;
        $data = array('name'=>$parentName, "coins" => $coins, "paymentLink" => $paymentLink);

        Mail::send('emails.email_payment_link', $data, function($message) use($subject, $parentEmail) {
            $message->to($parentEmail)->subject($subject);
        });

        return response()->json(['message' => 'coins added successfully']);
    }


    //updateNotificationSettings
    public function updateNotificationSettings(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
            'notification_settings' => 'required',
        ], [], []);

        $user = $request->user();
        $student_id = $request->student_id;
        //get the student
        $student = $this->userRepository->findByWhere([['id', '=', $student_id]], ['*'], ['studentGuardians'])->first();

        //check if the student is found
        if(!$student)
        {
            return response()->json(['message' => 'Student not found'], 404);
        }

        //check if the student is under the user
        $studentGuardians = $student->studentGuardians;
        $studentGuardians = $studentGuardians->map(function ($studentGuardian) {
            return $studentGuardian->guardian_id;
        });
        if(!$studentGuardians->contains($user->id))
        {
            return response()->json(['message' => 'You are not allowed to edit this student'], 403);
        }

        $notification_settings = $request->notification_settings;
        $notification_settings = collect($notification_settings);
        $notification_settings = $notification_settings->map(function ($notification_setting) {
            return [
                $notification_setting['key_name'] => $notification_setting['value'],
            ];
        });
        $notification_settings = $notification_settings->collapse();
        $notification_settings = $notification_settings->toArray();

        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();

        if($studentSetting)
        {
            $this->studentSettingRepository->update($studentSetting->id, $notification_settings);
        }
        else
        {
            $notification_settings['student_id'] = $student_id;
            $this->studentSettingRepository->create($notification_settings);
        }
        return response()->json(['success' => ['user updated successfully']]);
    }



    //updateProfile
    public function updateProfile(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'tel_number' => 'string',
            'address' => 'string',
        ], [], []);

        //convert tel number to integer
        try{
            $tel_number = intval($request->tel_number);
        }
        catch(\Exception $e)
        {
            return response()->json(['errors' => 'tel_number must be a number'], 422);
        }
        //get the user
        $user = $request->user();
        $user_id = $user->id;
        $user = $this->userRepository->findById($user_id);

        $user->tel_number = $request->tel_number;
        $user->address = $request->address;
        $user->save();

        return response()->json(['success' => ['user updated successfully'],
            'user' => $user]);
    }

    //revokeToken
    public function revokeToken(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'token_id' => 'required|integer',
        ], [], []);

        $token_id = $request->token_id;
        $user = $request->user();
        $user->tokens()->where('id', $token_id)->delete();
        return response()->json(['success' => ['token revoked successfully']]);
    }

    //addEditStudent
    public function addEditStudent(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'id' => 'integer|nullable',
            'name' => 'required|string',
            'student_identification' => 'string|required',
            'school_id' => 'integer|required',
            'notes' => 'string|required',
        ], [], []);

        // get auth user
        $authUser = $request->user();

        $name = $request->name;
        $student_identification = $request->student_identification;
        $school_id = $request->school_id;
        $notes = $request->notes;
        $parent_id = $authUser->id;
        //get the school
        $school = $this->userRepository->findById($school_id);
        if(!$school)
        {
            return response()->json(['message' => 'School does not exist'], 422);
        }

        //begin transaction
        DB::beginTransaction();
        try {
            if($request->id)
            {
                //edit the student
                $student = $this->userRepository->findById($request->id);
                $student->name = $name;
                $student->student_identification = $student_identification;
                $student->school_id = $school_id;
                $student->notes = $notes;
                $student->status_id = 4;
                $student->save();
            }
            else
            {
                //create the student
                $student = [
                    'name' => $name,
                    'student_identification' => $student_identification,
                    'school_id' => $school_id,
                    'notes' => $notes,
                    'role_id' => 6,
                    'status_id' => 4,
                ];
                $student = $this->userRepository->create($student);

                //add student to the parent
                $guardianStudent = [
                    'guardian_id' => $parent_id,
                    'student_id' => $student->id,
                ];

                $this->studentGuardianRepository->create($guardianStudent);

                $guardians = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 5], ['parent_id', '=', $parent_id]]);
                foreach ($guardians as $guardian) {
                    //add student to the parent
                    $guardianStudent = [
                        'guardian_id' => $guardian->id,
                        'student_id' => $student->id,
                    ];

                    $this->studentGuardianRepository->create($guardianStudent);
                }
            }

            //check if request has pic
            if($request->pic)
            {
                $picImage = $request->pic;
                $picImage = str_replace('data:image/png;base64,', '', $picImage);
                $picImage = str_replace(' ', '+', $picImage);
                $picImage = base64_decode($picImage);

                $imageName = time().'.png';
                $storagePath = Storage::url('avatars/'. $student->id);
                $imageAbsolutePath = public_path('/backend'.$storagePath);
                // check if the directory exists
                if (!file_exists($imageAbsolutePath)) {
                    mkdir($imageAbsolutePath, 0777, true);
                }
                file_put_contents($imageAbsolutePath.'/'.$imageName, $picImage);

                // Update user's avatar column on 'users' table
                $student->avatar = $storagePath .'/' . $imageName;
                $student->save();
            }
            else
            {
                $this->storeAvatar($student);
            }

            $student = $this->userRepository->findById($student->id, ['*'], ['studentSchool']);
            $student->school = $student->studentSchool;

            DB::commit();
            return response()->json([
                'success' => ['student created successfully'],
                'student' => $student,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //updateStudentPic
    public function updateStudentPic(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'id' => 'integer|nullable',
            'pic' => 'required',
        ], [], []);

        // get auth user
        $authUser = $request->user();

        $parent_id = $authUser->id;

        //begin transaction
        DB::beginTransaction();
        try {
            //edit the student
            $student = $this->userRepository->findById($request->id);

            $picImage = $request->pic;
            $picImage = str_replace('data:image/png;base64,', '', $picImage);
            $picImage = str_replace(' ', '+', $picImage);
            $picImage = base64_decode($picImage);

            $imageName = time().'.png';
            $storagePath = Storage::url('avatars/'. $student->id);
            $imageAbsolutePath = public_path('/backend'.$storagePath);
            // check if the directory exists
            if (!file_exists($imageAbsolutePath)) {
                mkdir($imageAbsolutePath, 0777, true);
            }
            file_put_contents($imageAbsolutePath.'/'.$imageName, $picImage);

            // Update user's avatar column on 'users' table
            $student->avatar = $storagePath .'/' . $imageName;
            $student->save();


            $student = $this->userRepository->findById($student->id, ['*'], ['studentSchool']);
            $student->school = $student->studentSchool;

            DB::commit();
            return response()->json([
                'success' => ['student created successfully'],
                'student' => $student,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


    //deleteSchool
    public function deleteSchool(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'school_id' => 'required|integer',
        ], [], []);

        $school_id = $request->school_id;
        $school = $this->userRepository->findById($school_id);
        if(!$school)
        {
            return response()->json(['message' => 'School does not exist'], 422);
        }
        if($school->role_id != 2)
        {
            return response()->json(['message' => 'User is not a school'], 422);
        }

        //delete the school
        $this->userRepository->deleteById($school_id);


        //delete the school from firebase
        $this->auth->deleteUser($school->uid);

        return response()->json(['success' => ['school deleted successfully']]);
    }

    //deleteStudent
    public function deleteStudent(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'id' => 'required|integer',
        ], [], []);

        $id = $request->id;
        $user = $request->user();
        $student = $this->userRepository->findById($id);
        if(!$student)
        {
            return response()->json(['message' => 'Student does not exist'], 422);
        }
        //check if the student belongs to the parent
        $guardianStudent = $this->studentGuardianRepository->findByWhere([['guardian_id', '=', $user->id], ['student_id', '=', $id]]);
        if(!$guardianStudent)
        {
            return response()->json(['message' => 'Student does not belong to the parent'], 422);
        }

        //student_settings
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $id])->first();
        if($studentSetting)
        {
            $studentSetting->delete();
        }

        //student_trips
        $studentTrips = $this->reservationRepository->allWhere(['*'], [], [['student_id', '=', $id]]);
        foreach ($studentTrips as $studentTrip) {
            $studentTrip->delete();
        }

        //delete the student
        $this->userRepository->deleteById($id);
        return response()->json(['success' => ['student deleted successfully']]);
    }

    //requestDeleteAccount
    public function requestDeleteAccount(Request $request)
    {
        $user = $request->user();
        $id = $user->id;
        $user = $this->userRepository->findById($id);
        if(!$user)
        {
            return response()->json(['message' => 'User does not exist'], 422);
        }
        //request the delete
        $user->request_delete_at = Carbon::now();
        $user->save();
        return response()->json(['success' => ['request sent successfully']]);
    }

    //deleteGuardian
    public function deleteGuardian(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'id' => 'required|integer',
        ], [], []);

        $id = $request->id;
        $user = $request->user();
        $guardian = $this->userRepository->findById($id);
        if(!$guardian)
        {
            return response()->json(['message' => 'Guardian does not exist'], 422);
        }

        if($guardian->role_id != 5)
        {
            return response()->json(['message' => 'User is not a guardian'], 422);
        }

        if($guardian->parent_id != $user->id)
        {
            return response()->json(['message' => 'Guardian does not belong to the parent'], 422);
        }

        DB::beginTransaction();
        try {
            //delete the guardian
            $this->userRepository->deleteById($id);
            //delete the guardian from firebase
            $this->auth->deleteUser($guardian->uid);

            DB::commit();
            return response()->json(['message' => 'Guardian deleted successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    //getStudentDetails
    public function getStudentDetails($student_id)
    {
        //get the student
        $student = $this->userRepository->findById($student_id, ['*'], ['studentSchool']);
        if(!$student)
        {
            return response()->json(['message' => 'Student does not exist'], 422);
        }
        $student->school = $student->studentSchool;
        $student->student_details = $this->getStudentSettings($student->id);
        return response()->json(['student' => $student]);
    }

    //setAbsentStudent
    public function setAbsentStudent(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'id' => 'required|integer',
        ], [], []);

        $student_id = $request->id;

        //get auth user id
        $auth_user = auth()->user();
        $parent = $this->userRepository->findById($auth_user->id, ['*'], ['guardianStudents.student']);
        //check if the student belongs to the parent
        $student = $parent->guardianStudents->where('student_id', $student_id)->first()->student;
        if($student == null)
        {
            return response()->json(['message' => 'The student does not belong to you'], 400);
        }

        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();

        if($studentSetting != null && $studentSetting->pickup_route_stop_id != null && $studentSetting->drop_off_route_stop_id != null)
        {
            //get last riding date in StudentTrip
            $studentTrip = StudentTrip::where('student_id', $student->id)->orderBy('planned_trip_id', 'desc')->first();
            if($studentTrip == null)
            {
                if($studentSetting->absent_on == null)
                {
                    $today = Carbon::today();
                    $absent_on = Carbon::parse($today)->addDays(1);
                    $studentSetting->absent_on = $absent_on;
                    $absent_on_formatted = $absent_on->toDateString();
                }
                else
                {
                    $studentSetting->absent_on = null;
                    $absent_on_formatted = null;
                }
                $studentSetting->save();
                return response()->json(['message' => ['student absent status updated successfully'], 'absent_on' => $absent_on_formatted]);
            }
            $planned_trip_id = $studentTrip->planned_trip_id;
            $plannedTrip = PlannedTrip::where('id', $planned_trip_id)->first();
            $plannedTripDate = $plannedTrip->planned_date;
            if($studentSetting->absent_on == null)
            {
                $absent_on = Carbon::parse($plannedTripDate)->addDays(1);
                $studentSetting->absent_on = $absent_on;
                $absent_on_formatted = $absent_on->toDateString();
            }
            else
            {
                //remove absence only if there is no planned trip for this student on the absent_on date

                $plannedStudentTrips = StudentTrip::with('plannedTrip')->where('student_id', $student->id)->get();
                foreach ($plannedStudentTrips as $plannedStudentTrip) {
                    $plannedTrip = $plannedStudentTrip->plannedTrip;
                    if($plannedTrip == null)
                    {
                        continue;
                    }
                    $plannedTripDate = $plannedTrip->planned_date;
                    if($plannedTripDate == $studentSetting->absent_on)
                    {
                        return response()->json(['message' => 'Can not remove absence. There is a planned trip for this student on the absence date'], 422);
                    }
                }
                $studentSetting->absent_on = null;
                $absent_on_formatted = null;
            }
            $studentSetting->save();
            return response()->json(['message' => ['student absent status updated successfully'], 'absent_on' => $absent_on_formatted]);
        }
        else
        {
            return response()->json(['message' => 'Please select a pickup and drop off stops for this student first'], 422);
        }
    }

    //addGuardian
    public function addGuardian(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email',
        ], [], []);

        $name = $request->name;
        $email = $request->email;

        //check if the email is already used
        $user = $this->userRepository->findByWhere([['email', '=', $email]])->first();
        if($user != null)
        {
            return response()->json(['message' => 'Can not add guardian. Email already used'], 422);
        }

        //create transaction
        DB::beginTransaction();
        try {
            $parent = $this->userRepository->findById($request->user()->id, ['*'], ['guardianStudents.student']);
            //create the user
            $userData = [
                'name' => $name,
                'email' => $email,
                'role_id' => 5,
                'status_id' => 1,
                'parent_id' => $parent->id,
                'password' => '12345678'
            ];
            $user = $this->userRepository->create($userData);
            $this->storeAvatar($user);
            //create the guardian on firebase
            $userProperties = [
                'email' => $email,
                'password' => '12345678',
            ];
            $createdFirebaseUser = $this->auth->createUser($userProperties);
            $user->uid = $createdFirebaseUser->uid;
            $user->save();

            // get all students of the parent
            $students = $parent->guardianStudents;
            //add guardian students
            foreach ($students as $student) {
                $guardianStudent = [
                    'guardian_id' => $user->id,
                    'student_id' => $student->student_id,
                ];
                $this->studentGuardianRepository->create($guardianStudent);
            }
            DB::commit();
            return response()->json(['message' => 'Guardian created successfully', 'guardian' => $user]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => ['guardian created successfully']]);
    }

    //assignStudentBus
    public function assignStudentBus(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
            'bus_id' => 'required|integer',
            'is_morning' => 'required|integer| between:0,1',
        ], [], []);

        $student_id = $request->student_id;
        $bus_id = $request->bus_id;
        $is_morning = $request->is_morning;
        $school = $request->user();
        $school_id = $school->id;

        //get the student
        $student = $this->userRepository->findById($student_id);
        if(!$student)
        {
            return response()->json(['message' => 'Student does not exist'], 422);
        }

        //check if student is in the school
        if($student->school_id != $school_id)
        {
            return response()->json(['message' => 'Student does not belong to the school'], 422);
        }

        //get the bus
        $bus = $this->busRepository->findById($bus_id);
        if(!$bus)
        {
            return response()->json(['message' => 'Bus does not exist'], 422);
        }

        $school = $this->userRepository->findById($school_id, ['*'], ['schoolStudents.studentSettings', 'schoolSettings']);
        $students = $school->schoolStudents;

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

        if($is_morning == 1)
        {
            if($bus->available_morning_seats == 0)
            {
                return response()->json(['message' => 'No available morning seats'], 422);
            }
        }
        else
        {
            if($bus->available_afternoon_seats == 0)
            {
                return response()->json(['message' => 'No available afternoon seats'], 422);
            }
        }

        //get the student settings
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id], ['*'], ['morningBus', 'afternoonBus'])->first();
        if($is_morning == 1)
        {
            $studentSetting->morning_bus_id = $bus_id;
        }
        else
        {
            $studentSetting->afternoon_bus_id = $bus_id;
        }
        $studentSetting->save();
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id], ['*'], ['morningBus', 'afternoonBus'])->first();
        return response()->json(['message' => 'Bus assigned successfully',
            'student_settings' => $studentSetting]);
    }


    //get-school-by-code
    public function getSchoolByCode($school_code)
    {
        $schoolSetting = SchoolSetting::where('school_code', $school_code)->first();
        if(!$schoolSetting)
        {
            return response()->json(['message' => 'School not found'], 404);
        }

        $school = $this->userRepository->findById($schoolSetting->school_id);
        if(!$school)
        {
            return response()->json(['message' => 'School not found'], 404);
        }
        return response()->json(['school' => $school]);
    }


    //printStudentCard
    public function printStudentCard(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'student_id' => 'required|integer',
        ], [], []);

        $student_id = $request->student_id;
        $student = $this->userRepository->findByWhere([['id', '=', $student_id]], ['*'], ['studentSchool', 'studentGuardians'])->first();
        if(!$student)
        {
            return response()->json(['message' => 'Student not found'], 404);
        }
        //get absolute path of avatar
        $avatar = public_path('/backend'.$student->avatar);
        $student->avatar = $avatar;

        //create qr code for the student
        $qrCode = QrCode::size(300)
            ->format('png')
            ->errorCorrection('H')
            ->generate($student->student_identification);
        $qrCodeName = $student->id.'.png';
        $storagePath = Storage::url('student_cards/'. $student->id);
        $qrCodeAbsolutePath = public_path('/backend'.$storagePath);
        // check if the directory exists
        if(!File::exists($qrCodeAbsolutePath)) {
            File::makeDirectory($qrCodeAbsolutePath, 0777, true, true);
        }
        // if (!file_exists($qrCodeAbsolutePath)) {
        //     mkdir($qrCodeAbsolutePath, 0777, true);
        // }
        file_put_contents($qrCodeAbsolutePath.'/'.$qrCodeName, $qrCode);
        //get absolute path of qr code
        $student->qr_code = $qrCodeAbsolutePath.'/'.$qrCodeName;
        Log::info('qrCodeAbsolutePath: '.$student->qr_code . ' avatar: '.$student->avatar);

        $pdf = Pdf::loadView('student_card', ['student' => $student]);
        $pdf = $pdf->output();

        $pdfName = 'Card.pdf';
        $storagePath = Storage::url('student_cards/'. $student->id);
        $pdfAbsolutePath = public_path('/backend'.$storagePath);
        // check if the directory exists
        if (!file_exists($pdfAbsolutePath)) {
            mkdir($pdfAbsolutePath, 0777, true);
        }
        file_put_contents($pdfAbsolutePath.'/'.$pdfName, $pdf);
        $parent = null;
        foreach ($student->studentGuardians as $guardian) {
            if($guardian->guardian->role_id == 4)
            {
                $parent = $guardian->guardian;
                break;
            }
        }
        if($parent == null)
        {
            return response()->json(['message' => 'Parent not found'], 404);
        }
        //send email to the parent with the pdf
        $parentEmail = $parent->email;
        $parentName = $parent->name;
        $subject = 'Student Card for '.$student->name;
        $attachment = $pdfAbsolutePath.'/'.$pdfName;
        $name = $student->name;
        $email = $student->email;

        $data = array('name'=>$parentName, "student_name" => $name);

        Mail::send('emails.email_student_card', $data, function($message) use($subject, $parentEmail, $pdfAbsolutePath, $pdfName) {
           $message->to($parentEmail)->subject($subject)->attach($pdfAbsolutePath.'/'.$pdfName);
        });

        return response()->json(['message' => 'Student card printed successfully']);

    }

    //fetchPlanDetailsForParent
    public function fetchPlanDetailsForParent(Request $request)
    {
        $this->validate($request, [
            'plan_id' => 'required|integer',
            'parent_id' => 'required|integer',
        ], [], []);

        $plan_id = $request->plan_id;
        //get parent
        $parent = $this->userRepository->findById($request->parent_id);
        //get the plan
        $plan = $this->validatePlan($plan_id, $parent);

        //check if the return is an error
        if($plan instanceof JsonResponse)
        {
            return $plan;
        }

        //check if the plan is available
        if($plan->availability == 1)
        {
            $available = $this->checkAvailability($parent, $plan);
            if(!$available)
            {
                return response()->json(['message' => 'You have already subscribed to this plan'], 422);
            }
        }

        //get currency
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        $paymentMethod = $this->getPaymentMethod();
        $key = "";
        if($paymentMethod == "razorpay")
        {
            $key = env('RAZORPAY_KEY');
        }
        else if($paymentMethod == "flutterwave")
        {
            $key = env('FLW_PUBLIC_KEY');
        }
        else if($paymentMethod == "paystack")
        {
            $key = env('PAYSTACK_PUBLIC_KEY');
        }
        else if($paymentMethod == "braintree")
        {
            $key = env('BRAINTREE_TOKENIZATION_KEY');
        }

        return response()->json(['plan' => $plan, 'parent' => $parent, 'currency_code' => $currency_code, 'payment_method' => $paymentMethod, 'key' => $key]);
    }


    private function downloadTemplate($userType)
    {
        $file = public_path(). "/backend/".$userType."_template.xlsx";
        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        return response()->download($file, $userType.'_template.xlsx', $headers);
    }


    //downloadTemplateForStudents
    public function downloadTemplateForStudents(Request $request)
    {
        return $this->downloadTemplate('student');
    }

    //uploadStudents from .xlsx file
    public function uploadStudents(Request $request)
    {
        Log::info('uploadStudents');
        //validate the request
        $this->validate($request, [
            'file' => 'required|mimes:xlsx,xls',
        ], [], []);

        $file = $request->file('file');
        $path = $file->getRealPath();
        //add .xlsx file to the file name
        $fileName = $file->getClientOriginalName();
        $fileName = explode('.', $fileName);
        $fileName = $fileName[0].'.xlsx';
        //get logged in user ID
        $user = $request->user();
        $schoolID = $user->id;
        //store file in storage folder
        $path = $file->storeAs('students/'.$schoolID, $fileName, 'public');
        //get absolute path of the file
        $path = public_path('/backend/storage/'.$path);
        $data = Excel::toArray(null, $path);
        //loop the data
        foreach ($data[0] as $key => $value) {
            //skip the first row
            if($key == 0)
            {
                continue;
            }
            //check if value has at least 5 elements
            if(count($value) < 5)
            {
                continue;
            }
            $parent_email = $value[0];
            Log::info('parent_email: '.$parent_email);
            $parent_name = $value[1];
            $create_parent_error = false;
            //check if the email is already used
            $parent = $this->userRepository->findByWhere([['email', '=', $parent_email]])->first();
            if($parent == null)
            {
                $createdFirebaseUser = null;
                //make transaction
                DB::beginTransaction();
                try {
                    //create the user
                    $userData = [
                        'name' => $parent_name,
                        'email' => $parent_email,
                        'role_id' => 4,
                        'status_id' => 1,
                        'password' => Hash::make('12345678'),
                    ];
                    $parent = $this->userRepository->create($userData);
                    $this->storeAvatar($parent);
                    //create the guardian on firebase
                    $userProperties = [
                        'email' => $parent_email,
                        'password' => '12345678',
                    ];
                    $createdFirebaseUser = $this->auth->createUser($userProperties);
                    $parent->uid = $createdFirebaseUser->uid;
                    $parent->save();
                    DB::commit();
                } catch (\Exception $e) {
                    Log::info($e->getMessage());
                    $create_parent_error = true;
                    DB::rollback();
                    //delete firebase user if user creation fails
                    if($createdFirebaseUser!=null)
                    {
                        $this->auth->deleteUser($createdFirebaseUser->uid);
                    }
                }
            }
            if($create_parent_error)
            {
                Log::info('create_parent_error' . $parent_email);
                continue;
            }
            for ($i = 2; $i < count($value); $i+=3) {
                $student_name = $value[$i];
                $student_identification = $value[$i+1];
                $school_id = $schoolID;
                $notes = $value[$i+2];
                if($student_name == null || $student_identification == null || $notes == null)
                {
                    continue;
                }
                //make transaction
                DB::beginTransaction();
                try {
                    $student_data = [
                        'name' => $student_name,
                        'student_identification' => $student_identification,
                        'school_id' => $school_id,
                        'notes' => $notes,
                        'role_id' => 6,
                        'status_id' => 1,
                    ];
                    $student = $this->userRepository->create($student_data);
                    $this->storeAvatar($student);
                    //add student to the parent
                    $guardianStudent = [
                        'guardian_id' => $parent->id,
                        'student_id' => $student->id,
                    ];
                    $this->studentGuardianRepository->create($guardianStudent);

                    $guardians = $this->userRepository->allWhere(['*'], [], [['role_id', '=', 5], ['parent_id', '=', $parent->id]]);
                    foreach ($guardians as $guardian) {
                        //add student to the parent
                        $guardianStudent = [
                            'guardian_id' => $guardian->id,
                            'student_id' => $student->id,
                        ];
                        $this->studentGuardianRepository->create($guardianStudent);
                    }
                    DB::commit();
                }
                catch (\Exception $e) {
                    Log::info($e->getMessage());
                    DB::rollback();
                }
            }
        }
    }

    //transferCoins
    public function transferCoins(Request $request)
    {
        //validate parent_id and coins
        $this->validate($request, [
            'parent_id' => 'required|integer',
            'coins' => 'required|integer',
        ], [], []);

        $school = $request->user();
        //guardians
        $guardians = $this->getAllSchoolGuardians($school->id);
        //get only the parents
        $parents = $guardians->filter(function ($guardian) {
            return $guardian->role_id == 4;
        });

        $parent_id = $request->parent_id;
        $coins = $request->coins;
        if(!$parents->contains('id', $parent_id))
        {
            return response()->json(['message' => 'Parent not found'], 404);
        }

        //get the parent
        $parent = $this->userRepository->findById($parent_id);

        //check if the parent exists and in school
        if(!$parent)
        {
            return response()->json(['message' => 'Parent not found'], 404);
        }

        //check if the school has enough coins
        if($school->balance < $coins)
        {
            return response()->json(['message' => 'School does not have enough coins'], 422);
        }

        //make transaction
        DB::beginTransaction();
        try {
            //transfer coins
            $school->balance -= $coins;
            $school->save();
            $parent->balance += $coins;
            $parent->save();
            DB::commit();
            return response()->json(['message' => 'Coins transferred successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
