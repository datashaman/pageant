<?php

use App\Models\User;

test('sidebar brand displays Pageant instead of Laravel Starter Kit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Pageant');
    $response->assertDontSee('Laravel Starter Kit');
});
