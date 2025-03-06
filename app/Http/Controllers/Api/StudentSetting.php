<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Repository\StudentSettingRepositoryInterface;

class StudentSetting extends Controller
{
    //
    private $studentSettingRepository;

    public function __construct(
        StudentSettingRepositoryInterface $studentSettingRepository)
    {
        $this->studentSettingRepository = $studentSettingRepository;
    }

    //getPickupDropOff
    public function getPickupDropOff(Request $request)
    {
        $this->validate($request, [
            'student_id' => 'required|integer',
        ], [], []);

        $student_id = $request->student_id;
        $parent = auth()->user();

        //check if the parent has this student
        $student = $parent->guardianStudents()->where('id', $student_id)->first();
        if($student == null)
        {
            return response()->json(['message' => 'You are not authorized to edit this student'], 403);
        }
        $studentSetting = $this->studentSettingRepository->findById($student_id);
        if($studentSetting == null)
        {
            return response()->json(['message' => 'Student settings not found'], 404);
        }
        else
        {
            return response()->json($studentSetting, 200);
        }
    }
}
