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
        'events' => [['event' => 'push', 'filters' => []]],
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
        'events' => [['event' => 'pull_request', 'filters' => []]],
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
        'events' => [['event' => 'pull_request_review', 'filters' => []]],
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
        'events' => [['event' => 'issue_comment', 'filters' => []]],
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
        'events' => [['event' => 'push', 'filters' => []]],
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
        'events' => [['event' => 'push', 'filters' => []]],
        'tools' => ['create_comment'],
    ]);
    $prAgent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'pr-agent',
        'events' => [['event' => 'pull_request', 'filters' => []]],
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
        'events' => [['event' => 'issues', 'filters' => []]],
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
        'events' => [['event' => 'push', 'filters' => []]],
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
        'events' => [['event' => 'pull_request', 'filters' => []]],
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

// --- Action-level filtering tests ---

it('dispatches only for matching action', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'issues.opened', 'filters' => []]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'opened',
        issue: ['number' => 1, 'title' => 'New', 'body' => '', 'user' => ['login' => 'dev']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});

it('does not dispatch for non-matching action', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'issues.opened', 'filters' => []]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'closed',
        issue: ['number' => 1, 'title' => 'New', 'body' => '', 'user' => ['login' => 'dev']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

it('bare event type matches all actions (backward compat)', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'issues', 'filters' => []]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'closed',
        issue: ['number' => 1, 'title' => 'Old', 'body' => '', 'user' => ['login' => 'dev']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});

// --- Label filter tests ---

it('dispatches when label filter matches', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'opened',
        issue: ['number' => 1, 'title' => 'Bug', 'body' => '', 'user' => ['login' => 'dev'], 'labels' => [['name' => 'bug'], ['name' => 'priority']]],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});

it('does not dispatch when label filter does not match', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'issues.opened', 'filters' => ['labels' => ['security']]]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubIssueReceived(
        action: 'opened',
        issue: ['number' => 1, 'title' => 'Bug', 'body' => '', 'user' => ['login' => 'dev'], 'labels' => [['name' => 'bug']]],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

// --- Base branch filter tests ---

it('dispatches when base_branch filter matches', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPullRequestReceived(
        action: 'opened',
        pullRequest: ['number' => 10, 'title' => 'Feature', 'body' => '', 'head' => ['ref' => 'feat'], 'base' => ['ref' => 'main']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});

it('does not dispatch when base_branch filter does not match', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPullRequestReceived(
        action: 'opened',
        pullRequest: ['number' => 10, 'title' => 'Feature', 'body' => '', 'head' => ['ref' => 'feat'], 'base' => ['ref' => 'develop']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

// --- Branch glob pattern tests ---

it('dispatches when branch glob pattern matches', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'push', 'filters' => ['branches' => ['main', 'release/*']]]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/release/v1.0',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});

it('does not dispatch when branch glob pattern does not match', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'push', 'filters' => ['branches' => ['main']]]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    event(new GitHubPushReceived(
        ref: 'refs/heads/feature/foo',
        before: 'aaa111',
        after: 'bbb222',
        commits: [],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

// --- Combined filters ---

it('requires all filter types to match (AND logic)', function () {
    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => [['event' => 'pull_request.opened', 'filters' => ['labels' => ['bug'], 'base_branch' => 'main']]],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    // Labels match but base_branch does not
    event(new GitHubPullRequestReceived(
        action: 'opened',
        pullRequest: ['number' => 10, 'title' => 'Fix', 'body' => '', 'head' => ['ref' => 'fix'], 'base' => ['ref' => 'develop'], 'labels' => [['name' => 'bug']]],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertNothingPushed();
});

// --- Legacy string format backward compat ---

it('supports legacy string event format', function () {
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
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, 1);
});
