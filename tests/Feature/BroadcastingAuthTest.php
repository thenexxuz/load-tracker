<?php

use App\Models\User;

it('returns a 400 when socket_id is missing from broadcast auth', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/broadcasting/auth', ['channel_name' => 'private-test']);

    $response->assertStatus(400);
});

it('continues processing when socket_id is provided', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => 'private-test',
            'socket_id' => '1234.5678',
        ]);

    // because the channel isn't defined the controller will eventually
    // return 403; at minimum we can assert it is not the 400 we added.
    $resp->assertStatus(403);
});
