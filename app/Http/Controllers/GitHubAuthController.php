<?php

namespace App\Http\Controllers;

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

        $user = User::query()
            ->where('github_id', $githubUser->getId())
            ->orWhere('email', $githubUser->getEmail())
            ->first();

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
                'email' => $githubUser->getEmail(),
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

    private function syncGitHubOrganizations(User $user, string $token): void
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
        ])->get('https://api.github.com/user/orgs');

        if ($response->failed()) {
            return;
        }

        foreach ($response->json() as $githubOrg) {
            $organization = Organization::firstOrCreate(
                ['slug' => Str::slug($githubOrg['login'])],
                ['title' => $githubOrg['login']],
            );

            $user->organizations()->syncWithoutDetaching($organization->id);
        }
    }
}
