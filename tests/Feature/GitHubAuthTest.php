<?php

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('redirects to github', function () {
    $response = $this->get(route('auth.github'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('github.com');
});

it('creates a new user from github callback', function () {
    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $socialiteUser = createSocialiteUser();

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $response = $this->get(route('auth.github.callback'));

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('github_id', 123456)->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->avatar_url)->toBe('https://avatars.githubusercontent.com/u/123456');
});

it('links existing user by email on github callback', function () {
    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $existingUser = User::factory()->create(['email' => 'test@example.com']);

    $socialiteUser = createSocialiteUser();

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $this->get(route('auth.github.callback'));

    $existingUser->refresh();

    expect($existingUser->github_id)->toBe(123456)
        ->and($existingUser->avatar_url)->toBe('https://avatars.githubusercontent.com/u/123456');
});

it('links existing user by github_id on callback', function () {
    Http::fake(['https://api.github.com/user/installations' => Http::response([])]);

    $existingUser = User::factory()->create([
        'email' => 'old@example.com',
        'github_id' => 123456,
    ]);

    $socialiteUser = createSocialiteUser();

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $this->get(route('auth.github.callback'));

    $existingUser->refresh();

    expect($existingUser->github_id)->toBe(123456)
        ->and($existingUser->avatar_url)->toBe('https://avatars.githubusercontent.com/u/123456');

    expect(User::query()->count())->toBe(1);
});

it('does not allow email/password login for github-only users', function () {
    User::factory()->create([
        'email' => 'github@example.com',
        'password' => null,
        'github_id' => 999,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'github@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

it('fetches primary email from github api when email is private', function () {
    Http::fake([
        'https://api.github.com/user/emails' => Http::response([
            ['email' => 'secondary@example.com', 'primary' => false, 'verified' => true],
            ['email' => 'primary@example.com', 'primary' => true, 'verified' => true],
        ]),
        'https://api.github.com/user/installations' => Http::response([]),
    ]);

    $socialiteUser = createSocialiteUser(email: null);

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $this->get(route('auth.github.callback'));

    $user = User::query()->where('github_id', 123456)->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('primary@example.com');
});

it('creates organizations from github callback', function () {
    Http::fake([
        'https://api.github.com/user/installations' => Http::response([
            'installations' => [
                ['id' => 1001, 'account' => ['login' => 'Acme Corp', 'type' => 'Organization']],
                ['id' => 1002, 'account' => ['login' => 'widgets-inc', 'type' => 'Organization']],
            ],
        ]),
    ]);

    $socialiteUser = createSocialiteUser();

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $this->get(route('auth.github.callback'));

    $user = User::query()->where('github_id', 123456)->first();

    expect(Organization::query()->count())->toBe(2);
    expect($user->organizations)->toHaveCount(2);
    expect(Organization::query()->where('slug', 'acme-corp')->exists())->toBeTrue();
    expect(Organization::query()->where('slug', 'widgets-inc')->exists())->toBeTrue();

    expect(GithubInstallation::query()->count())->toBe(2);
    expect(GithubInstallation::query()->where('installation_id', 1001)->exists())->toBeTrue();
    expect(GithubInstallation::query()->where('installation_id', 1002)->exists())->toBeTrue();
});

it('attaches user to existing organization by slug', function () {
    $existingOrg = Organization::factory()->create([
        'name' => 'Existing Org',
        'slug' => 'existing-org',
    ]);

    Http::fake([
        'https://api.github.com/user/installations' => Http::response([
            'installations' => [
                ['id' => 2001, 'account' => ['login' => 'existing-org', 'type' => 'Organization']],
            ],
        ]),
    ]);

    $socialiteUser = createSocialiteUser();

    Socialite::shouldReceive('driver')
        ->with('github')
        ->andReturn(mockSocialiteDriver($socialiteUser));

    $this->get(route('auth.github.callback'));

    $user = User::query()->where('github_id', 123456)->first();

    expect(Organization::query()->count())->toBe(1);
    expect($user->organizations->first()->id)->toBe($existingOrg->id);
});

function createSocialiteUser(?string $email = 'test@example.com'): SocialiteUser
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

function mockSocialiteDriver(SocialiteUser $socialiteUser): object
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
