<?php

namespace App\Services;

use App\Helpers\GoogleMapsHelper;

class FuelEfficiencyService
{
    public static function calculateEfficiency($bus)
    {
        $distance = GoogleMapsHelper::getDistance($bus->start_location, $bus->end_location);
        $efficiency = 10; // Assume 10 km/l or fetch from database
        $fuelUsed = $distance / $efficiency;

        return [
            'distance' => $distance,
            'fuel_used' => $fuelUsed,
            'efficiency' => $efficiency
        ];
    }
}
