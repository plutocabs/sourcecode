<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Repository\SettingRepositoryInterface;
use App\Repository\SchoolSettingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class SettingController extends Controller
{
    private $settingRepository;
    private $schoolSettingRepository;

    public function __construct(
        SettingRepositoryInterface $settingRepository,
        SchoolSettingRepositoryInterface $schoolSettingRepository)
    {
        $this->settingRepository = $settingRepository;
        $this->schoolSettingRepository = $schoolSettingRepository;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json($this->settingRepository->all(
            ['*'], ['currency']
        )->first(), 200);
    }

    public function update(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'currency_id' => 'required|integer',
            'distance_to_stop_to_mark_arrived' => 'required|numeric|gt:0',
            'allow_ads_in_driver_app' => 'boolean',
            'allow_ads_in_parent_app' => 'boolean',
            'simple_mode' => 'boolean',
            'hide_schools' => 'boolean',
            'hide_payment_parents' => 'boolean',
            'distance_to_drop_off' => 'required|numeric|gt:0',
            'distance_to_pick_up' => 'required|numeric|gt:0',
            'distance_to_slow_down' => 'required|numeric|gt:0',
            'max_search_radius' => 'required|numeric|gt:0',
            'otp_required' => 'boolean',
        ], [], []);


        $settings = $this->settingRepository->all()->first();

        $newSettings = [
            'currency_id' => $request->currency_id,
            'distance_to_stop_to_mark_arrived' => $request->distance_to_stop_to_mark_arrived,
            'allow_ads_in_driver_app' => $request->allow_ads_in_driver_app ?? false,
            'allow_ads_in_parent_app' => $request->allow_ads_in_parent_app ?? false,
            'simple_mode' => $request->simple_mode ?? false,
            'hide_schools' => $request->hide_schools ?? false,
            'hide_payment_parents' => $request->hide_payment_parents ?? false,
            'distance_to_drop_off' => $request->distance_to_drop_off,
            'distance_to_pick_up' => $request->distance_to_pick_up,
            'distance_to_slow_down' => $request->distance_to_slow_down,
            'max_search_radius' => $request->max_search_radius,
            'otp_required' => $request->otp_required ?? false,
        ];
        if(abs($newSettings['distance_to_slow_down'] - $newSettings['distance_to_drop_off']) < 100 || abs($newSettings['distance_to_slow_down'] - $newSettings['distance_to_pick_up']) < 100)
        {
            return response()->json(['errors' => ['Distance to slow down must be greater than distance to pick up and drop off']], 422);
        }
        $this->settingRepository->update($settings->id, $newSettings);
    }


    public function updateSchool(Request $request)
    {
        //log request data
        Log::info('updateSchool', ['request' => $request->all()]);
        //validate the request
        $this->validate($request, [
            'saturday' => 'nullable|integer|between:0,1',
            'sunday' => 'nullable|integer|between:0,1',
            'monday' => 'nullable|integer|between:0,1',
            'tuesday' => 'nullable|integer|between:0,1',
            'wednesday' => 'nullable|integer|between:0,1',
            'thursday' => 'nullable|integer|between:0,1',
            'friday' => 'nullable|integer|between:0,1',
            'address' => 'required|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'place_id' => 'required|string',
        ], [], []);

        $school = $request->user();
        $school_id = $school->id;

        $settings = $this->schoolSettingRepository->findByWhere(['school_id' => $school_id])->first();

        $newSettings = [
            'saturday' => $request->has('saturday') ? $request->saturday : 0,
            'sunday' => $request->has('sunday') ? $request->sunday : 0,
            'monday' => $request->has('monday') ? $request->monday : 0,
            'tuesday' => $request->has('tuesday') ? $request->tuesday : 0,
            'wednesday' => $request->has('wednesday') ? $request->wednesday : 0,
            'thursday' => $request->has('thursday') ? $request->thursday : 0,
            'friday' => $request->has('friday') ? $request->friday : 0,
            'address' => $request->address,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'place_id' => $request->place_id,

        ];
        if($settings == null)
        {
            $newSettings['school_id'] = $school_id;
            $this->schoolSettingRepository->create($newSettings);
            return;
        }
        else
        {
            $this->schoolSettingRepository->update($settings->id, $newSettings);
        }
    }

    public function getPrivacyPolicy(Request $request)
    {
        $privacy = file_get_contents(public_path('privacy_local.html'));

        return response()->json(['privacy' => $privacy]);
    }

    public function getPrivacy(Request $request)
    {
        $privacy = file_get_contents(public_path('privacy.html'));

        return response()->json(['privacy' => $privacy]);
    }


    public function updatePrivacyPolicy(Request $request)
    {
        //validate the request
        $validator = Validator::make($request->all(), [
            'privacy' => 'required|string',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        //privacy_local
        file_put_contents(public_path('privacy_local.html'), $request->privacy);

        //add html headers
        $request->privacy = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Privacy Policy</title></head><body>' . $request->privacy . '</body></html>';

        file_put_contents(public_path('privacy.html'), $request->privacy);

        return response()->json(['success' => ['Privacy Policy updated successfully']]);
    }

    public function updateTerms(Request $request)
    {
        //validate the request
        $validator = Validator::make($request->all(), [
            'terms' => 'required|string',
        ]);

        if ($validator->fails()) {
            //pass validator errors as errors object for ajax response
            return response()->json(['errors' => $validator->errors()], 422);
        }

        file_put_contents(public_path('terms_local.html'), $request->terms);

        //add html headers
        $request->terms = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Terms and Conditions</title></head><body>' . $request->terms . '</body></html>';

        file_put_contents(public_path('terms.html'), $request->terms);

        return response()->json(['success' => ['Terms updated successfully']]);
    }

    public function getTerms(Request $request)
    {
        //get terms
        $terms = file_get_contents(public_path('terms_local.html'));

        return response()->json(['terms' => $terms]);
    }

    //getUserSettings
    public function getUserSettings(Request $request)
    {
        $settings = $this->settingRepository->all()->first();
        return response()->json(['settings' => $settings]);
    }


    //getSchoolSettings
    public function getSchoolSettings(Request $request)
    {
        $school = $request->user();
        $school_id = $school->id;

        $settings = $this->schoolSettingRepository->findByWhere(['school_id' => $school_id])->first();
        return response()->json($settings);
    }

}
