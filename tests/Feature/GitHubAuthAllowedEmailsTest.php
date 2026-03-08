<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

it('allows login when email is in allowed list', function () {
    config(['app.allowed_emails' => 'allowed@example.com']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createSocialiteUser(email: 'allowed@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'allowed@example.com')->exists())->toBeTrue();
});

it('rejects login when email is not in allowed list', function () {
    config(['app.allowed_emails' => 'allowed@example.com']);

    $socialiteUser = createSocialiteUser(email: 'denied@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
    expect(User::query()->where('email', 'denied@example.com')->exists())->toBeFalse();
});

it('allows any email when allowed list is empty', function () {
    config(['app.allowed_emails' => '']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createSocialiteUser(email: 'anyone@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'anyone@example.com')->exists())->toBeTrue();
});

it('supports multiple emails in allowed list', function () {
    config(['app.allowed_emails' => 'first@example.com, second@example.com']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createSocialiteUser(email: 'second@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'second@example.com')->exists())->toBeTrue();
});

it('performs case-insensitive email comparison', function () {
    config(['app.allowed_emails' => 'User@Example.COM']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createSocialiteUser(email: 'user@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'user@example.com')->exists())->toBeTrue();
});

it('rejects null email when allowed list is non-empty', function () {
    config(['app.allowed_emails' => 'allowed@example.com']);

    Http::fake([
        'https://api.github.com/user/emails' => Http::response([], 404),
    ]);

    $socialiteUser = createSocialiteUser(email: null);

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
