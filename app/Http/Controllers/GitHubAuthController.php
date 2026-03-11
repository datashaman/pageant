<?php

namespace App\Http\Controllers;

use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\User;
use App\Services\GitHubService;
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
            ->scopes(['user:email', 'repo'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $email = $githubUser->getEmail() ?? $this->fetchPrimaryGitHubEmail($githubUser->token);

        if (! $this->isEmailAllowed($email)) {
            return redirect()->route('home')->withErrors([
                'email' => 'You are not authorized to access this application.',
            ]);
        }

        $query = User::query()->where('github_id', $githubUser->getId());

        if ($email) {
            $query->orWhere('email', $email);
        }

        $user = $query->first();

        $tokenData = [
            'github_id' => $githubUser->getId(),
            'avatar_url' => $githubUser->getAvatar(),
            'github_token' => $githubUser->token,
            'github_refresh_token' => $githubUser->refreshToken,
            'github_username' => $githubUser->getNickname(),
        ];

        if ($user) {
            $user->update($tokenData);
        } else {
            $user = User::create(array_merge([
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'email' => $email,
            ], $tokenData));
        }

        $this->syncGitHubOrganizations($user);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function isEmailAllowed(?string $email): bool
    {
        $allowedEmails = array_filter(
            array_map(
                static fn (string $value): string => strtolower(trim($value)),
                explode(',', (string) config('app.allowed_emails', ''))
            )
        );

        if (empty($allowedEmails)) {
            return true;
        }

        if ($email === null) {
            return false;
        }

        return in_array(strtolower($email), $allowedEmails, true);
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

    private function syncGitHubOrganizations(User $user): void
    {
        try {
            $installations = app(GitHubService::class)->listAppInstallations();
        } catch (\Throwable) {
            return;
        }

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

            if (! $user->current_organization_id) {
                $user->update(['current_organization_id' => $orgIds[0]]);
            }
        }
    }
}
