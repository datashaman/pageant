<?php

namespace App\Http\Controllers;

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['user:email', 'read:org'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $email = $githubUser->getEmail() ?? $this->fetchPrimaryGitHubEmail($githubUser->token);

        $query = User::query()->where('github_id', $githubUser->getId());

        if ($email) {
            $query->orWhere('email', $email);
        }

        $user = $query->first();

        if ($user) {
            $user->update([
                'github_id' => $githubUser->getId(),
                'github_token' => $githubUser->token,
                'github_refresh_token' => $githubUser->refreshToken,
                'avatar_url' => $githubUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $email,
                'github_id' => $githubUser->getId(),
                'github_token' => $githubUser->token,
                'github_refresh_token' => $githubUser->refreshToken,
                'avatar_url' => $githubUser->getAvatar(),
            ]);
        }

        $this->syncGitHubOrganizations($user, $githubUser->token);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function fetchPrimaryGitHubEmail(string $token): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get('https://api.github.com/user/emails');

        if ($response->failed()) {
            return null;
        }

        $primary = collect($response->json())->firstWhere('primary', true);

        return $primary['email'] ?? null;
    }

    private function syncGitHubOrganizations(User $user, string $token): void
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get('https://api.github.com/user/installations');

        if ($response->failed()) {
            return;
        }

        $installations = $response->json('installations', []);

        $orgIds = [];

        foreach ($installations as $installation) {
            $account = $installation['account'] ?? [];
            $login = $account['login'] ?? null;

            if (! $login) {
                continue;
            }

            $organization = Organization::firstOrCreate(
                ['slug' => Str::slug($login)],
                ['name' => $login],
            );

            GithubInstallation::updateOrCreate(
                ['installation_id' => $installation['id']],
                [
                    'organization_id' => $organization->id,
                    'account_login' => $login,
                    'account_type' => $account['type'] ?? 'User',
                    'permissions' => $installation['permissions'] ?? [],
                    'events' => $installation['events'] ?? [],
                ],
            );

            $orgIds[] = $organization->id;
        }

        if ($orgIds) {
            $user->organizations()->syncWithoutDetaching($orgIds);
        }
    }
}
