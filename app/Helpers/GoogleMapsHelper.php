<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class GoogleMapsHelper
{
    public static function getDistance($origin, $destination)
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');

        $response = Http::get("https://maps.googleapis.com/maps/api/distancematrix/json", [
            'origins' => $origin,
            'destinations' => $destination,
            'key' => $apiKey,
        ]);

        $data = $response->json();

        // Check if response contains valid data
        if (!isset($data['rows'][0]['elements'][0]['distance']['value'])) {
            return 0; // Return 0 if no distance found
        }

        return $data['rows'][0]['elements'][0]['distance']['value'] / 1000; // Convert meters to km
    }
}
