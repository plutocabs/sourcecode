<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Repository\ChargeRepositoryInterface;
use App\Repository\SettingRepositoryInterface;

class ChargeController extends Controller
{
    //
    private $chargeRepository;
    private $settingRepository;
    public function __construct(
        ChargeRepositoryInterface $chargeRepository,
        SettingRepositoryInterface $settingRepository)
    {
        $this->chargeRepository = $chargeRepository;
        $this->settingRepository = $settingRepository;
    }

    public function index(Request $request)
    {
        $charges = $this->chargeRepository->all(['*'], ['plan', 'school','parent']);
        $totalPaid = 0;
        // loop through the charges and add payer field
        foreach ($charges as $charge) {
            if($charge->school_id != null)
            {
                $charge->payer = $charge->school;
                $charge->payer_type = 'school';
            }
            else
            {
                $charge->payer = $charge->parent;
                $charge->payer_type = 'parent';
            }
            $totalPaid += $charge->price;
        }
        //get currency symbol
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        return response()->json(['charges' => $charges, 'totalPaid' => $totalPaid . ' ' . $currency_code], 200);
    }

    //getSchoolCharges
    public function getSchoolCharges(Request $request)
    {
        //get auth user
        $user = auth()->user();
        // get all charges of the school ordered by date
        $charges = $this->chargeRepository->allWhere(['*'], [], [['school_id', '=', $user->id]], true);
        $totalPaid = 0;
        // loop through the charges and add payer field
        foreach ($charges as $charge) {
            $totalPaid += $charge->price;
        }
        //get currency symbol
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        return response()->json(['charges' => $charges, 'totalPaid' => $totalPaid . ' ' . $currency_code], 200);
    }
}
