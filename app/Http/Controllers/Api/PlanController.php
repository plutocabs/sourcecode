<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Repository\PlanRepositoryInterface;
use App\Repository\SettingRepositoryInterface;
use App\Traits\UserUtils;
class PlanController extends Controller
{
    use UserUtils;
    //
    private $planRepository;
    private $settingRepository;
    public function __construct(PlanRepositoryInterface $planRepository,
                                SettingRepositoryInterface $settingRepository)
    {
        $this->planRepository = $planRepository;
        $this->settingRepository = $settingRepository;
    }

    public function index(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'planType' => 'required|in:0,1',
        ], [], []);
        $planType = $request->planType;
        //get all plans
        $plans = $this->planRepository->allWhere(['*'], [], [['plan_type', '=', $planType]]);
        return response()->json($plans, 200);
    }

    // return available plans for a school
    public function availablePlans(Request $request)
    {
        //get auth user
        $user = auth()->user();
        $plans = $this->getAvailablePlans($user);

        // get current payment method
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

        //get currency
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;

        return response()->json(['plans' => $plans, 'paymentMethod' => $paymentMethod, 'currency_code' => $currency_code, 'email' => $user->email, 'name' => $user->name, 'key' => $key], 200);
    }

    //show
    public function show($plan_id)
    {
        //get the plan
        $plan = $this->planRepository->findById($plan_id);
        //set currency symbol
        $currency = $this->settingRepository->all(['*'], ['currency'])->first();
        $currency_code = $currency->currency->code;
        $plan->currency_code = $currency_code;

        $paymentMethod = $this->getPaymentMethod();
        if($paymentMethod == "braintree")
        {
            // get the tokenization key
            $tokenization_key = env('BRAINTREE_TOKENIZATION_KEY');
            $plan->tokenization_key = $tokenization_key;
        }
        else if($paymentMethod == "razorpay")
        {
            // // get the tokenization key
            // $tokenization_key = env('BRAINTREE_TOKENIZATION_KEY');
            // $plan->tokenization_key = $tokenization_key;
        }
        else if($paymentMethod == "flutterwave")
        {
            // // get the tokenization key
            // $tokenization_key = env('BRAINTREE_TOKENIZATION_KEY');
            // $plan->tokenization_key = $tokenization_key;
        }
        else if($paymentMethod == "paytabs")
        {
            // // get the tokenization key
            // $tokenization_key = env('BRAINTREE_TOKENIZATION_KEY');
            // $plan->tokenization_key = $tokenization_key;
        }

        return response()->json($plan, 200);
    }

    //createEdit
    public function createEdit(Request $request)
    {
        //validate the request
        $this->validate($request, [
            'plan' => 'required',
            'plan.id' => 'integer|nullable',
            'plan.coin_count' => 'required|integer',
            'plan.price' => 'required|integer',
            'plan.plan_type' => 'required|in:0,1',
            'plan.name' => 'required|string',
            'plan.availability' => 'required|in:1,2',
        ], [], []);

        $update = false;
        $plan_id = null;
        if(array_key_exists('id', $request->plan) && $request->plan['id'] != null)
        {
            //update
            $update = true;
            $plan_id = $request->plan['id'];
        }
        if($update)
        {
            //update the plan data
            $this->planRepository->update($plan_id, $request->plan);
            return response()->json(['success' => ['plan updated successfully']]);
        }
        else
        {
            //create new plan
            $this->planRepository->create($request->plan);
            return response()->json(['success' => ['plan created successfully']]);
        }
    }

    //destroy
    public function destroy($plan_id)
    {
        //delete the plan
        $this->planRepository->deleteById($plan_id);
        return response()->json(['success' => ['plan deleted successfully']]);
    }
}
