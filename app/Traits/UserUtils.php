<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Laravolt\Avatar\Avatar;
use App\Models\User;
use App\Models\Reservation;
use App\Models\StudentGuardian;
use App\Models\StudentSetting;
use App\Models\UserNotification;
use App\Models\PlannedTrip;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;

trait UserUtils {

    public function deleteAccounts()
    {
        $auth = app('firebase.auth');

        $deleteDate = now()->subDays(3);
        //check the request to delete accounts
        $users = User::with('guardianStudents')->whereNotNull('request_delete_at')->where('request_delete_at', '<=', $deleteDate)->get();
        Log::info('Deleting accounts ' . $users->count());
        //remove the user's data from the database if the request_delete_at is 3 days ago
        DB::beginTransaction();
        try {
            foreach ($users as $user) {
                //get the user's uid
                $uid = $user->uid;
                try{
                    $auth->getUser($uid);
                    //delete the user's data from Firebase
                    $auth->deleteUser($uid);
                } catch (UserNotFound $e) {
                    //if the user is not found in Firebase, delete the user's data from the database
                    Log::info('User not found in Firebase ' . $uid);
                }
                $user_id = $user->id;
                $this->deleteUserData($user_id);
                //delete the user's data from the database
                $user->delete();
                //delete all students of this user
                $students = $user->guardianStudents();
                foreach ($students as $student) {
                    //student_settings
                    $studentSetting = StudentSetting::where('student_id', $student->id)->first();
                    if($studentSetting)
                    {
                        $studentSetting->delete();
                    }
                    //student_trips
                    $studentTrips = Reservation::where('student_id', $student->id)->get();
                    foreach ($studentTrips as $studentTrip) {
                        $studentTrip->delete();
                    }
                    $student_id = $student->id;
                    //delete the student
                    $student->delete();
                    $this->deleteUserData($student_id);
                }
                Log::info('Deleted account ' . $user->id);
            }
            //get all students
            $allStudents = User::where('role_id', 6)->get();
            Log::info('All students ' . $allStudents->count());
            //filter students that have no parent
            $studentsWithNoParent = [];
            foreach ($allStudents as $student) {
                $guardians = StudentGuardian::where('student_id', $student->id)->get();
                if($guardians->count() == 0)
                {
                    $studentsWithNoParent[] = $student;
                }
            }
            $studentsWithNoParent = collect($studentsWithNoParent);
            Log::info('Students with no parent ' . $studentsWithNoParent->count());
            //delete students with no parent
            foreach ($studentsWithNoParent as $student) {
                Log::info('Deleting student with no parent ' . $student->id);
                //student_settings
                $studentSetting = StudentSetting::where('student_id', $student->id)->first();
                if($studentSetting)
                {
                    $studentSetting->delete();
                }
                //student_trips
                $studentTrips = Reservation::where('student_id', $student->id)->get();
                foreach ($studentTrips as $studentTrip) {
                    $studentTrip->delete();
                }
                //delete the student
                $student_id = $student->id;
                $student->delete();
                $this->deleteUserData($student_id);
                Log::info('Deleted student with no parent ' . $student_id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::info($e->getMessage());
        }
    }

    public function endTrips()
    {
        $dateBefore = now();
        $dateBefore->subDays(1);
        // get all PlannedTrip that has no reservations and planned_date is less than $dateBefore
        $openTrips = PlannedTrip::with('reservations')
        ->where('planned_date', '<=', $dateBefore)
        ->where('started_at', '!=', null)
        ->where('ended_at', null)
        ->get();
        //update openTrips in DB using Where in
        $openTripsIds = $openTrips->pluck('id');
        PlannedTrip::whereIn('id', $openTripsIds)->update(['ended_at' => now()]);
    }

    public function deleteUserData($user_id)
    {
        // delete the avatars folder
        if(Storage::disk('public')->exists('avatars/'.$user_id)){
            Storage::disk('public')->deleteDirectory('avatars/'.$user_id);
        }

        //delete the images folder
        if(Storage::disk('public')->exists('images/'.$user_id)){
            Storage::disk('public')->deleteDirectory('images/'.$user_id);
        }

        // delete the student's folder
        if(Storage::disk('public')->exists('student_cards/'.$user_id)){
            Storage::disk('public')->deleteDirectory('student_cards/'.$user_id);
        }
    }

    public function createToken($user, $name, $tokenAbility){
        $token = $user->createToken($name, $tokenAbility);
        return $token->plainTextToken;
    }

    //store avatar
    public function storeAvatar($user){
        $avatar = (new Avatar)->create($user->name)->getImageObject()->encode('png');
        //check if avatars/'.$user->id directory exists, if not create it
        if(!Storage::disk('public')->exists('avatars/'.$user->id)){
            Storage::disk('public')->makeDirectory('avatars/'.$user->id);
        }
        //store the image to storage/avatars/user-id/avatar.png
        $stored = Storage::disk('public')->put('avatars/'.$user->id.'/avatar.png', (string) $avatar);
        if($stored){
            $user->avatar = '/storage/avatars/'.$user->id.'/avatar.png';
            $user->save();
        }
    }

    //get Payment Method based on .env
    public function getPaymentMethod(){
        $merchantId = env('BRAINTREE_MERCHANT_ID');
        $api_key = env('RAZORPAY_KEY');
        $publicKey = env('FLW_PUBLIC_KEY');
        $paytabsProfileId = env('paytabs_profile_id');
        $payStackPublicKey = env('PAYSTACK_PUBLIC_KEY');
        $stripeKey = env('STRIPE_KEY');
        if($merchantId){
            $paymentMethod = 'braintree';
        } else if($api_key){
            $paymentMethod = 'razorpay';
        }
        else if($publicKey){
            $paymentMethod = 'flutterwave';
        }
        else if($paytabsProfileId){
            $paymentMethod = 'paytabs';
        }
        else if($payStackPublicKey){
            $paymentMethod = 'paystack';
        }
        else if($stripeKey){
            $paymentMethod = 'stripe';
        }
        else
        {
            $paymentMethod = 'none';
        }
        return $paymentMethod;
    }

    // return available plans for a user
    public function getAvailablePlans($user)
    {
        $planType = $user->role_id == 2 ? 0 : 1;

        //get all plans
        $plans = $this->planRepository->allWhere(['*'], [], [['plan_type', '=', $planType]]);

        // filter out plans that are one time buy if the user has already bought a plan
        //get the school's charges
        if($user->role_id == 2)
            $charges = $user->schoolCharges;
        else
            $charges = $user->parentCharges;

        //get the school's one time buy plans
        $oneTimeBuyPlans = $plans->where('availability', 1);
        //loop through the one time buy plans and remove the ones that the user has already bought
        foreach ($oneTimeBuyPlans as $oneTimeBuyPlan) {
            foreach ($charges as $charge) {
                if($charge->plan_id == $oneTimeBuyPlan->id)
                {
                    $plans = $plans->where('id', '!=', $oneTimeBuyPlan->id);
                }
            }
        }
        $plans = $plans->values();
        return $plans;
    }


    private function canViewRoute($route_id, $user_id)
    {
        //check if the route belongs to the school
        $route = $this->routeRepository->findById($route_id);
        if($route->school_id != $user_id)
        {
            return false;
        }
        return true;
    }

    private function canViewStop($stop_id, $user_id)
    {
        //check if the stop belongs to the school
        $stop = $this->stopRepository->findById($stop_id);
        if($stop->school_id != $user_id)
        {
            return false;
        }
        return true;
    }

    //canViewTrip
    private function canViewTrip($trip_id, $user_id)
    {
        //check if the trip belongs to the school
        $trip = $this->tripRepository->findById($trip_id);
        if($trip->school_id != $user_id)
        {
            return false;
        }
        return true;
    }

    //get pickup and drop-off times for a student
    public function getStudentSettings($student_id)
    {
        $pickup_pick_time = null;
        $pickup_drop_time = null;
        $dropOff_pick_time = null;
        $dropOff_drop_time = null;
        $pickup_stop = null;
        $dropOff_stop = null;
        $pickup_trip = null;
        $dropOff_trip = null;
        $pickup_route = null;
        $dropOff_route = null;
        $absent_on = null;

        //get the student
        $student = $this->userRepository->findById($student_id);
        $studentSetting = $this->studentSettingRepository->findByWhere(['student_id' => $student_id])->first();
        if($studentSetting != null)
        {
            //get pickup and drop-off route_stop
            if($studentSetting->pickup_route_stop_id != null)
            {
                $pickup_route_stop = $this->routeStopRepository->findById($studentSetting->pickup_route_stop_id, ['*'], ['route', 'stop']);
                $pickup_route = $pickup_route_stop->route;
                $pickup_stop = $pickup_route_stop->stop;
                $pickup_trip = $this->tripRepository->findById($studentSetting->pickup_trip_id, ['*'], ['tripDetails']);
                //get the student's pickup and drop-off trips' tripDetails
                $pickup_tripDetails = $pickup_trip->tripDetails;

                //loop through the pickup tripDetails and get the pickup stop time
                foreach ($pickup_tripDetails as $pickup_tripDetail) {
                    if($pickup_tripDetail->stop_id == $pickup_stop->id)
                    {
                        $pickup_pick_time = $pickup_tripDetail->planned_timestamp;
                        break;
                    }
                }
                $pickup_drop_time = $pickup_tripDetails->last()->planned_timestamp;
            }
            if($studentSetting->drop_off_route_stop_id != null)
            {
                $dropOff_route_stop = $this->routeStopRepository->findById($studentSetting->drop_off_route_stop_id, ['*'], ['route', 'stop']);
                $dropOff_route = $dropOff_route_stop->route;
                $dropOff_stop = $dropOff_route_stop->stop;
                $dropOff_trip = $this->tripRepository->findById($studentSetting->drop_off_trip_id, ['*'], ['tripDetails']);
                $dropOff_tripDetails = $dropOff_trip->tripDetails;

                //loop through the drop-off tripDetails and get the drop-off stop time
                foreach ($dropOff_tripDetails as $dropOff_tripDetail) {
                    if($dropOff_tripDetail->stop_id == $dropOff_stop->id)
                    {
                        $dropOff_drop_time = $dropOff_tripDetail->planned_timestamp;
                        break;
                    }
                }
                $dropOff_pick_time = $dropOff_tripDetails->first()->planned_timestamp;
            }
            //get the student's absent_on
            $absent_on = $studentSetting->absent_on;

            $bus_arrived_at_pickup_location_notification_on_off = $studentSetting->bus_arrived_at_pickup_location_notification_on_off;
            $bus_arrived_at_drop_off_location_notification_on_off = $studentSetting->bus_arrived_at_drop_off_location_notification_on_off;
            $next_stop_is_your_pickup_location_notification_on_off = $studentSetting->next_stop_is_your_pickup_location_notification_on_off;
            $student_is_picked_up_notification_on_off = $studentSetting->student_is_picked_up_notification_on_off;
            $bus_arrived_at_school_notification_on_off = $studentSetting->bus_arrived_at_school_notification_on_off;
            $student_is_missed_pickup_notification_on_off = $studentSetting->student_is_missed_pickup_notification_on_off;
            $bus_near_drop_off_location_notification_on_off = $studentSetting->bus_near_drop_off_location_notification_on_off;
            $bus_near_pickup_location_notification_by_distance = $studentSetting->bus_near_pickup_location_notification_by_distance;

            return [
                'pickup_pick_time' => $pickup_pick_time,
                'pickup_drop_time' => $pickup_drop_time,
                'drop_off_pick_time' => $dropOff_pick_time,
                'drop_off_drop_time' => $dropOff_drop_time,
                'pickup_stop' => $pickup_stop,
                'drop_off_stop' => $dropOff_stop,
                'pickup_trip' => $pickup_trip,
                'drop_off_trip' => $dropOff_trip,
                'pickup_route' => $pickup_route,
                'drop_off_route' => $dropOff_route,
                'absent_on' => $absent_on,
                'bus_arrived_at_pickup_location_notification_on_off' => $bus_arrived_at_pickup_location_notification_on_off,
                'bus_arrived_at_drop_off_location_notification_on_off' => $bus_arrived_at_drop_off_location_notification_on_off,
                'next_stop_is_your_pickup_location_notification_on_off' => $next_stop_is_your_pickup_location_notification_on_off,
                'student_is_picked_up_notification_on_off' => $student_is_picked_up_notification_on_off,
                'bus_arrived_at_school_notification_on_off' => $bus_arrived_at_school_notification_on_off,
                'student_is_missed_pickup_notification_on_off' => $student_is_missed_pickup_notification_on_off,
                'bus_near_drop_off_location_notification_on_off' => $bus_near_drop_off_location_notification_on_off,
                'bus_near_pickup_location_notification_by_distance' => $bus_near_pickup_location_notification_by_distance,
                'pickup_lat' => $studentSetting->pickup_lat,
                'pickup_lng' => $studentSetting->pickup_lng,
                'pickup_address' => $studentSetting->pickup_address,
                'pickup_place_id' => $studentSetting->pickup_place_id,
                'drop_off_lat' => $studentSetting->drop_off_lat,
                'drop_off_lng' => $studentSetting->drop_off_lng,
                'drop_off_address' => $studentSetting->drop_off_address,
                'drop_off_place_id' => $studentSetting->drop_off_place_id,
                'morning_bus_id' => $studentSetting->morning_bus_id,
                'afternoon_bus_id' => $studentSetting->afternoon_bus_id,
            ];
        }
    }


    public function isTokenUsed($idx, $tokens) {
        //check if the token is used before from index 0 to index idx
        for ($i=0; $i < $idx; $i++) {
            if($tokens[$i] == $tokens[$idx]){
                return true;
            }
        }
        return false;
    }


    public function sendSingleNotification($deviceToken, $message_content, $notificationId)
    {
        try
        {
            $message = CloudMessage::fromArray([
                'token' => $deviceToken,
                'notification' => [
                    'title' => 'Alert',
                    'body' => $message_content,
                ],
                'data' => [
                    "title" => "Alert",
                    "body" => $message_content,
                    "id" => $notificationId,
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'alert' => [
                                'body' => $message_content,
                            ],
                        ],
                    ],
                ],
            ]);

            $report = $this->messaging->send($message);
            Log::info("sendSingleNotification "  . $deviceToken . ", " . $message_content . ", " . $notificationId);
        }
        catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }

    public function sendMultipleNotifications($deviceTokens, $message_content)
    {
        $message = CloudMessage::fromArray([
            'notification' => [
                'title' => 'Alert',
                'body' => $message_content,
            ],
            'data' => [
                "title" => "Alert",
                "body" => $message_content,
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'alert' => [
                            'body' => $message_content,
                        ],
                    ],
                ],
            ],
        ]);

        $report = $this->messaging->sendMulticast($message, $deviceTokens);

        if ($report->hasFailures()) {
            $failures = $report->getItems();
            foreach ($failures as $failure) {
                $error = $failure->error();
                Log::info($error->getMessage());
            }
        }
    }

    //send notification to user
    public function sendNotificationToUser($user, $text)
    {
        //needs notificationRepository, studentGuardianRepository, messaging
        if($user->role_id == 3 || $user->role_id == 4 || $user->role_id == 5)
        {
            $newNotification = $this->notificationRepository->create([
                'user_id' => $user->id,
                'message' => $text,
                'seen' => 0,
            ]);
            $notificationId = $newNotification->id;
            $this->sendSingleNotification($user->fcm_token, $text, $notificationId);
        }
        else if($user->role_id == 6)
        {
            //get the guardians of the student
            $guardians = $this->studentGuardianRepository->allWhere(['*'], ['guardian'], [['student_id', '=', $user->id]]);

            $guardians = $guardians->pluck('guardian');
            $guardianIds = $guardians->pluck('id')->toArray();
            $tokens = $guardians->pluck('fcm_token')->toArray();
            for ($i=0; $i < count($guardianIds); $i++) {
                //foreach ($guardianIds as $guardianId) {
                    $guardianId = $guardianIds[$i];

                    $newNotification = $this->notificationRepository->create([
                        'user_id' => $guardianId,
                        'message' => $text,
                        'seen' => 0,
                    ]);
                    $notificationId = $newNotification->id;
                    $token = $tokens[$i];
                    if($token == null)
                    {
                        continue;
                    }
                    if($i == 0 || !$this->isTokenUsed($i, $tokens)){
                        $this->sendSingleNotification($token, $text, $notificationId);
                    }
                }
            }
        }
    }
