<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('allows login when email is in allowed list', function () {
    config(['app.allowed_emails' => 'allowed@example.com']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createAllowedEmailsSocialiteUser('allowed@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockAllowedEmailsSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'allowed@example.com')->exists())->toBeTrue();
});

it('rejects login when email is not in allowed list', function () {
    config(['app.allowed_emails' => 'allowed@example.com']);

    $socialiteUser = createAllowedEmailsSocialiteUser('denied@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockAllowedEmailsSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
    expect(User::query()->where('email', 'denied@example.com')->exists())->toBeFalse();
});

it('allows any email when allowed list is empty', function () {
    config(['app.allowed_emails' => '']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createAllowedEmailsSocialiteUser('anyone@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockAllowedEmailsSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'anyone@example.com')->exists())->toBeTrue();
});

it('supports multiple emails in allowed list', function () {
    config(['app.allowed_emails' => 'first@example.com, second@example.com']);

    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createAllowedEmailsSocialiteUser('second@example.com');

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockAllowedEmailsSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));
    expect(User::query()->where('email', 'second@example.com')->exists())->toBeTrue();
});

function createAllowedEmailsSocialiteUser(string $email): SocialiteUser
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = 123456;
    $socialiteUser->name = 'Test User';
    $socialiteUser->email = $email;
    $socialiteUser->avatar = 'https://avatars.githubusercontent.com/u/123456';
    $socialiteUser->token = 'github-token';
    $socialiteUser->refreshToken = 'github-refresh-token';
    $socialiteUser->nickname = 'testuser';

    return $socialiteUser;
}

function mockAllowedEmailsSocialiteDriver(SocialiteUser $socialiteUser): object
{
    return new class($socialiteUser)
    {
        public function __construct(private SocialiteUser $user) {}

        public function user(): SocialiteUser
        {
            return $this->user;
        }
    };
}
