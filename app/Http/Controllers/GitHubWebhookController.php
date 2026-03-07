<?php

namespace App\Http\Controllers;

use App\Models\GithubInstallation;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $this->verifySignature($request);

        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        return match ($event) {
            'installation' => $this->handleInstallation($payload),
            'installation_repositories' => $this->handleInstallationRepositories($payload),
            default => $this->handleDefault($event, $payload),
        };
    }

    private function verifySignature(Request $request): void
    {
        $secret = config('services.github.webhook_secret');

        if (! $secret) {
            abort(500, 'GitHub webhook secret not configured.');
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            abort(403, 'Missing signature.');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid signature.');
        }
    }

    private function handleInstallation(array $payload): JsonResponse
    {
        $action = $payload['action'];
        $installation = $payload['installation'];

        return match ($action) {
            'created' => $this->installationCreated($installation),
            'deleted' => $this->installationDeleted($installation),
            'suspend' => $this->installationSuspended($installation),
            'unsuspend' => $this->installationUnsuspended($installation),
            default => response()->json(['message' => 'Unhandled installation action: '.$action]),
        };
    }

    private function installationCreated(array $installation): JsonResponse
    {
        $login = $installation['account']['login'];

        $organization = Organization::firstOrCreate(
            ['slug' => Str::slug($login)],
            ['title' => $login],
        );

        GithubInstallation::updateOrCreate(
            ['installation_id' => $installation['id']],
            [
                'organization_id' => $organization->id,
                'account_login' => $login,
                'account_type' => $installation['account']['type'],
                'permissions' => $installation['permissions'] ?? [],
                'events' => $installation['events'] ?? [],
            ]
        );

        return response()->json(['message' => 'Installation created.']);
    }

    private function installationDeleted(array $installation): JsonResponse
    {
        GithubInstallation::query()
            ->where('installation_id', $installation['id'])
            ->delete();

        return response()->json(['message' => 'Installation deleted.']);
    }

    private function installationSuspended(array $installation): JsonResponse
    {
        GithubInstallation::query()
            ->where('installation_id', $installation['id'])
            ->update(['suspended_at' => now()]);

        return response()->json(['message' => 'Installation suspended.']);
    }

    private function installationUnsuspended(array $installation): JsonResponse
    {
        GithubInstallation::query()
            ->where('installation_id', $installation['id'])
            ->update(['suspended_at' => null]);

        return response()->json(['message' => 'Installation unsuspended.']);
    }

    private function handleInstallationRepositories(array $payload): JsonResponse
    {
        Log::info('GitHub installation_repositories event received.', [
            'action' => $payload['action'],
            'installation_id' => $payload['installation']['id'],
        ]);

        return response()->json(['message' => 'Installation repositories event received.']);
    }

    private function handleDefault(string $event, array $payload): JsonResponse
    {
        Log::info("GitHub webhook event received: {$event}");

        return response()->json(['message' => "Event '{$event}' received."]);
    }
}
