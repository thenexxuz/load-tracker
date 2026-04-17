<?php

use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('supervisor', 'web');
});

test('location import maps exported csv columns and normalizes type', function (): void {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $csv = implode("\n", [
        'ID,Guid,Short Code,Name,Address,City,State,ZIP,Country,Latitude,Longitude,Type,Created At',
        'legacy-id-1,61d3872b-0c34-4fcd-b729-460a380b487d,MCCO04,MCCOLLISTERS VA,10103 Residency Road Suite 130,Manassas,VA,20110,US,38.737994,-77.526329,0,2026-04-13 11:17:09',
    ]);

    $file = UploadedFile::fake()->createWithContent('locations.csv', $csv);

    $this->actingAs($supervisor)
        ->post(route('admin.locations.import'), ['file' => $file])
        ->assertRedirect()
        ->assertSessionHas('success', 'Locations imported successfully!');

    $location = Location::query()->where('short_code', 'MCCO04')->first();

    expect($location)->not->toBeNull()
        ->and($location?->name)->toBe('MCCOLLISTERS VA')
        ->and($location?->city)->toBe('Manassas')
        ->and($location?->state)->toBe('VA')
        ->and($location?->type)->toBe('pickup');
});
