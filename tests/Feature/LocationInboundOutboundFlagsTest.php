<?php

use App\Models\Location;

it('assigns outbound to pickups and inbound to distribution centers and recycling locations', function (): void {
    $pickup = Location::factory()->pickup()->create();
    $distributionCenter = Location::factory()->distribution_center()->create();
    $recycling = Location::factory()->create([
        'type' => 'recycling',
    ]);

    expect($pickup->fresh()?->outbound)->toBeTrue()
        ->and($pickup->fresh()?->inbound)->toBeFalse()
        ->and($distributionCenter->fresh()?->inbound)->toBeTrue()
        ->and($distributionCenter->fresh()?->outbound)->toBeFalse()
        ->and($recycling->fresh()?->inbound)->toBeTrue()
        ->and($recycling->fresh()?->outbound)->toBeFalse();
});

it('recalculates inbound and outbound when a location type changes', function (): void {
    $location = Location::factory()->pickup()->create();

    $location->update([
        'type' => 'distribution_center',
    ]);

    expect($location->fresh()?->inbound)->toBeTrue()
        ->and($location->fresh()?->outbound)->toBeFalse();
});
