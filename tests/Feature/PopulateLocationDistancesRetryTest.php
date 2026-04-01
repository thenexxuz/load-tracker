<?php

use App\Models\Location;
use Illuminate\Support\Facades\Http;

it('re-geocodes and retries when a recycling route exceeds 2 hours', function (): void {
    // Http::fake must come first so the LocationObserver's geocoding calls during
    // factory creation are intercepted (returned empty so lat/lng stays null).
    Http::fake([
        'https://api.mapbox.com/geocoding/*' => Http::sequence()
            ->push(['features' => []], 200)   // Rec observer geocode → fails, lat stays null
            ->push(['features' => []], 200)   // DC observer geocode → fails, lat stays null
            ->push(['features' => [['center' => [-97.1, 32.7]]]], 200)   // DC initial geocode
            ->push(['features' => [['center' => [-118.2, 34.0]]]], 200)  // Rec initial geocode
            ->push(['features' => [['center' => [-97.2, 32.8]]]], 200)   // DC refreshCoordinates
            ->push(['features' => [['center' => [-118.3, 34.1]]]], 200), // Rec refreshCoordinates
        'https://api.mapbox.com/directions/*' => Http::sequence()
            ->push(['routes' => [['distance' => 2_000_000, 'duration' => 8_500, 'geometry' => ['coordinates' => []]]]], 200) // 2h 21m — triggers retry
            ->push(['routes' => [['distance' => 2_000_000, 'duration' => 7_000, 'geometry' => ['coordinates' => []]]]], 200), // 1h 56m — retry result
    ]);

    $rec = Location::factory()->create([
        'type' => 'recycling',
        'short_code' => 'REC1',
        'latitude' => null,
        'longitude' => null,
    ]);

    $dc = Location::factory()->create([
        'type' => 'distribution_center',
        'short_code' => 'DC1',
        'latitude' => null,
        'longitude' => null,
        'recycling_location_id' => $rec->id,
    ]);

    $this->artisan('locations:populate-distances', ['--force' => true])
        ->expectsOutputToContain('exceeds 2 hours')
        ->expectsOutputToContain('DC1 → Recycling REC1: 1 hr 56 min')
        ->assertSuccessful();

    // Both locations should have refreshed coordinates from the retry geocoding pass
    expect($dc->fresh()->latitude)->toBe(32.8)
        ->and($dc->fresh()->longitude)->toBe(-97.2)
        ->and($rec->fresh()->latitude)->toBe(34.1)
        ->and($rec->fresh()->longitude)->toBe(-118.3);

    // 6 geocoding (2 observer during factory + 2 initial in command + 2 refreshes) + 2 directions = 8
    Http::assertSentCount(8);
});

it('warns and skips retry when coordinate refresh fails', function (): void {
    Http::fake([
        'https://api.mapbox.com/geocoding/*' => Http::sequence()
            ->push(['features' => []], 200)   // Rec observer geocode → fails
            ->push(['features' => []], 200)   // DC observer geocode → fails
            ->push(['features' => [['center' => [-97.1, 32.7]]]], 200)  // DC initial geocode
            ->push(['features' => [['center' => [-118.2, 34.0]]]], 200) // Rec initial geocode
            ->push(['features' => []], 200),                            // DC refreshCoordinates fails — Rec skipped via short-circuit
        'https://api.mapbox.com/directions/*' => Http::sequence()
            ->push(['routes' => [['distance' => 2_000_000, 'duration' => 8_500, 'geometry' => ['coordinates' => []]]]], 200), // >2 hours
    ]);

    $rec = Location::factory()->create([
        'type' => 'recycling',
        'short_code' => 'REC2',
        'latitude' => null,
        'longitude' => null,
    ]);

    $dc = Location::factory()->create([
        'type' => 'distribution_center',
        'short_code' => 'DC2',
        'latitude' => null,
        'longitude' => null,
        'recycling_location_id' => $rec->id,
    ]);

    $this->artisan('locations:populate-distances', ['--force' => true])
        ->expectsOutputToContain('exceeds 2 hours')
        ->assertSuccessful();

    // Coordinate refresh failed — DC coords remain null
    expect($dc->fresh()->latitude)->toBeNull()
        ->and($dc->fresh()->longitude)->toBeNull();

    // 2 observer + 2 initial + 1 failed DC refresh = 5 geocoding, + 1 directions = 6 total
    Http::assertSentCount(6);
});
