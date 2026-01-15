<?php

namespace App\Observers;

use App\Models\Location;

class LocationObserver
{
    public function saving(Location $location): void
    {
        // Only allow distribution centers to have a recycling location
        if ($location->recycling_location_id !== null) {
            if ($location->type !== 'distribution_center') {
                throw new \Exception('Only distribution centers can be assigned a recycling location.');
            }
        }
    }

    public function saved(Location $location): void
    {
        // Optional: ensure inverse relationship consistency
        if ($location->recycling_location_id !== null) {
            $recyclingLoc = Location::find($location->recycling_location_id);
            if ($recyclingLoc && $recyclingLoc->recycling_location_id !== $location->id) {
                $recyclingLoc->updateQuietly([
                    'recycling_location_type' => Location::class,
                    'recycling_location_id' => $location->id,
                ]);
            }
        }
    }
}
