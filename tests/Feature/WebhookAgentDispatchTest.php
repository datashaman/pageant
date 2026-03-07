<?php

use App\Events\GitHubCommentReceived;
use App\Events\GitHubIssueReceived;
use App\Events\GitHubPullRequestReceived;
use App\Events\GitHubPullRequestReviewReceived;
use App\Events\GitHubPushReceived;
use App\Jobs\RunWebhookAgent;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
        'installation_id' => 12345,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('dispatches RunWebhookAgent for push event when agent subscribes to push', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['push'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [['id' => 'bbb222', 'message' => 'Fix bug']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) use ($agent) {
        return $job->agent->id === $agent->id
            && $job->repoFullName === 'acme/widgets';
    });
});

it('dispatches RunWebhookAgent for pull_request event', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['pull_request'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPullRequestReceived(
        action: 'opened',
        pullRequest: ['number' => 10, 'title' => 'Add feature', 'body' => 'desc', 'head' => ['ref' => 'feat'], 'base' => ['ref' => 'main']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class);
});

it('dispatches RunWebhookAgent for pull_request_review event', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['pull_request_review'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPullRequestReviewReceived(
        action: 'submitted',
        review: ['id' => 1, 'state' => 'approved', 'body' => 'LGTM', 'user' => ['login' => 'reviewer']],
        pullRequest: ['number' => 10, 'title' => 'Add feature'],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class);
});

it('dispatches RunWebhookAgent for issue_comment event', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['issue_comment'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubCommentReceived(
        action: 'created',
        comment: ['id' => 123, 'body' => 'Nice!', 'user' => ['login' => 'dev']],
        issue: ['number' => 42, 'title' => 'Fix bug'],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class);
});

it('does not dispatch for untracked repos', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['push'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'unknown/repo'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

it('only dispatches agents subscribed to the specific event', function () {
    $pushAgent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'push-agent',
        'events' => ['push'],
        'tools' => ['create_comment'],
    ]);
    $prAgent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'pr-agent',
        'events' => ['pull_request'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach([$pushAgent->id, $prAgent->id]);

    event(new GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
    Queue::assertPushed(RunWebhookAgent::class, function ($job) use ($pushAgent) {
        return $job->agent->id === $pushAgent->id;
    });
});

it('dispatches RunWebhookAgent for issues event', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['issues'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'labeled',
        issue: ['number' => 42, 'title' => 'Fix bug', 'body' => 'desc', 'user' => ['login' => 'dev']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
        label: ['name' => 'priority'],
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) use ($agent) {
        return $job->agent->id === $agent->id
            && $job->issueNumber === 42;
    });
});

it('does not dispatch when agent is disabled', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['push'],
        'tools' => ['create_comment'],
        'enabled' => false,
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

it('does not dispatch when agent has no matching events', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['pull_request'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});
