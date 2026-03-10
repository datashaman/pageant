<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('welcome page can be rendered', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('welcome page shows tagline and description for guests', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('AI Agent Orchestration for GitHub')
        ->assertSee('Connect repositories with intelligent agents')
        ->assertSee('Sign in with GitHub')
        ->assertDontSee('Go to Dashboard');
});

test('authenticated users are redirected to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

test('welcome page mentions GitHub integration', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('GitHub');
});

test('welcome page hides email login link in non-local environment', function () {
    expect(app()->environment('local'))->toBeFalse();

    $response = $this->get(route('home'));

    $response->assertOk()->assertDontSee('Log in with email and password');
});

test('welcome page shows email login link when in local environment', function () {
    $originalEnv = app()->environment();
    $this->app->instance('env', 'local');

    $response = $this->get(route('home'));

    $this->app->instance('env', $originalEnv);

    $response->assertOk()->assertSee('Log in with email and password');
});
