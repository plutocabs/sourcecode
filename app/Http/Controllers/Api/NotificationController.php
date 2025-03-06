<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Repository\ComplaintRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Repository\NotificationRepositoryInterface;

use Illuminate\Support\Facades\Log;
use App\Models\AuthSetting;
class NotificationController extends Controller
{

    private $complaintRepository;
    private $userRepository;
    private $notificationRepository;
    public function __construct(
        ComplaintRepositoryInterface $complaintRepository,
        UserRepositoryInterface $userRepository,
        NotificationRepositoryInterface $notificationRepository
    )
    {
        $this->complaintRepository = $complaintRepository;
        $this->userRepository = $userRepository;
        $this->notificationRepository = $notificationRepository;
    }
    //
    public function index()
    {
        $schoolAdminUser = auth()->user();

        //count how many un resolved complaints
        $unResolvedComplaints = $this->complaintRepository->allWhere(['*'],[],['status' => 0],false);
        $unResolvedComplaintsCount = $unResolvedComplaints->count();


        //count how many drivers under review
        $driversUnderReview = $this->userRepository->allWhere(['*'],[],['status_id' => 4, 'role_id' => 3, 'school_id' => $schoolAdminUser->id],false);
        $driversUnderReviewCount = $driversUnderReview->count();

        //count how many students under review
        $studentsUnderReview = $this->userRepository->allWhere(['*'],[],['status_id' => 4, 'role_id' => 6, 'school_id' => $schoolAdminUser->id],false);
        $studentsUnderReviewCount = $studentsUnderReview->count();


        //count how many students need bus assignment
        $studentsNeedBusAssignment = $this->userRepository->allWhere(['*'],['studentSettings'],['status_id' => 1, 'role_id' => 6, 'school_id' => $schoolAdminUser->id],false);
        $studentsNeedBusAssignmentCount = $studentsNeedBusAssignment->filter(function($student){
            return $student->studentSettings != null &&
            ($student->studentSettings->morning_bus_id == null ||
            $student->studentSettings->afternoon_bus_id == null);
        })->count();


        $school = $this->userRepository->findById($schoolAdminUser->id, ['*'], ['schoolSettings']);

        if($school->schoolSettings == null)
        {
            $school->schoolSettings = new \stdClass();
            $school->schoolSettings->address = null;
            $school->schoolSettings->lat = null;
            $school->schoolSettings->lng = null;
        }

        $schoolAddressIsSet = $school->schoolSettings->address != null && $school->schoolSettings->lat != null && $school->schoolSettings->lng != null;

        $secure_key = null;
        $authSetting = AuthSetting::first();
        if(!($authSetting == null || $authSetting->secure_key == null
        || $authSetting->u1 == null
        || $authSetting->u2 == null
        || $authSetting->u3 == null))
        {
            $secure_key = $authSetting->secure_key;
        }
        return response()->json([
            'unResolvedComplaintsCount' => $unResolvedComplaintsCount,
            'driversUnderReviewCount' => $driversUnderReviewCount,
            'studentsUnderReviewCount' => $studentsUnderReviewCount,
            'studentsNeedBusAssignmentCount' => $studentsNeedBusAssignmentCount,
            'setSchoolAddressCount' => $schoolAddressIsSet ? 0 : 1,
            'adminName' => $schoolAdminUser->name,
            'adminId' => $schoolAdminUser->id,
            'adminAvatar' => $schoolAdminUser->avatar,
            'secureKey' => $secure_key
        ]);
    }

    //get all notifications
    public function listAll(Request $request)
    {
        $user = $request->user();
        // order based on created_at
        $notifications = $this->notificationRepository->allWhere(['*'],[],['user_id' => $user->id],true);

        return response()->json([
            'notifications' => $notifications
        ]);
    }

    //markAllAsSeen
    public function markAllAsSeen(Request $request)
    {
        $user = $request->user();
        $this->notificationRepository->bulkUpdate(['seen' => 1],['user_id' => $user->id]);
        return response()->json([
            'message' => 'success'
        ]);
    }

    //deleteAllNotifications
    public function deleteAllNotifications(Request $request)
    {
        $user = $request->user();
        $this->notificationRepository->deleteWhere(['user_id' => $user->id]);
        return response()->json([
            'message' => 'success'
        ]);
    }

    //markAsSeen
    public function markAsSeen(Request $request)
    {
        // validate id
        $request->validate([
            'id' => 'required|integer'
        ]);
        $user = $request->user();
        $notificationId = $request->id;
        $this->notificationRepository->update($notificationId, ['seen' => 1]);
        return response()->json([
            'message' => 'success'
        ]);
    }
}
