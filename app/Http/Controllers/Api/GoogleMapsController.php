<?php

namespace App\Http\Controllers\Api;

use App\Helpers\GoogleMapsHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoogleMapsController extends Controller
{
    public function getDistance(Request $request)
    {
        $request->validate([
            'origin' => 'required|string',
            'destination' => 'required|string',
        ]);

        $distance = GoogleMapsHelper::getDistance($request->origin, $request->destination);

        return response()->json([
            'origin' => $request->origin,
            'destination' => $request->destination,
            'distance_km' => $distance
        ]);
    }
}
