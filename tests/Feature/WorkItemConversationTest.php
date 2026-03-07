<?php

use App\Events\WorkItemCreated;
use App\Jobs\RunWebhookAgent;
use App\Listeners\HandleWorkItemCreated;
use App\Models\Agent;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
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

it('dispatches WorkItemCreated event from AI CreateWorkItemTool', function () {
    Event::fake([WorkItemCreated::class]);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    WorkItemCreated::dispatch($workItem, 'acme/widgets', 12345);

    Event::assertDispatched(WorkItemCreated::class, function ($event) use ($workItem) {
        return $event->workItem->id === $workItem->id
            && $event->repoFullName === 'acme/widgets'
            && $event->installationId === 12345;
    });
});

it('HandleWorkItemCreated dispatches agents subscribed to work_item_created', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['work_item_created'],
        'tools' => ['create_comment'],
    ]);
    $this->repo->agents()->attach($agent);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'title' => 'Fix login bug',
        'description' => 'Users cannot log in',
        'source_url' => 'https://github.com/acme/widgets/issues/42',
    ]);

    $listener = new HandleWorkItemCreated;
    $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) use ($agent) {
        return $job->agent->id === $agent->id
            && $job->repoFullName === 'acme/widgets'
            && $job->installationId === 12345
            && $job->issueNumber === 42;
    });
});

it('HandleWorkItemCreated extracts issue number from source reference', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['work_item_created'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#99',
    ]);

    $listener = new HandleWorkItemCreated;
    $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === 99;
    });
});

it('HandleWorkItemCreated passes null issue number when no hash in source reference', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['work_item_created'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);

    $listener = new HandleWorkItemCreated;
    $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === null;
    });
});

it('does not dispatch agents when none subscribe to work_item_created', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['push'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    $workItem = WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $listener = new HandleWorkItemCreated;
    $listener->handle(new WorkItemCreated($workItem, 'acme/widgets', 12345));

    Queue::assertNothingPushed();
});

it('passes issue number through listeners for issue events', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['issues'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    event(new \App\Events\GitHubIssueReceived(
        action: 'labeled',
        issue: ['number' => 42, 'title' => 'Fix bug', 'body' => '', 'user' => ['login' => 'dev']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
        label: ['name' => 'urgent'],
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === 42;
    });
});

it('passes issue number through listeners for comment events', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['issue_comment'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    event(new \App\Events\GitHubCommentReceived(
        action: 'created',
        comment: ['id' => 1, 'body' => 'Test', 'user' => ['login' => 'dev']],
        issue: ['number' => 42, 'title' => 'Fix bug'],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === 42;
    });
});

it('passes issue number through listeners for pull request events', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['pull_request'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    event(new \App\Events\GitHubPullRequestReceived(
        action: 'opened',
        pullRequest: ['number' => 15, 'title' => 'Add feature', 'body' => '', 'head' => ['ref' => 'feat'], 'base' => ['ref' => 'main']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === 15;
    });
});

it('passes issue number through listeners for pull request review events', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['pull_request_review'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    event(new \App\Events\GitHubPullRequestReviewReceived(
        action: 'submitted',
        review: ['id' => 1, 'state' => 'approved', 'body' => 'LGTM', 'user' => ['login' => 'reviewer']],
        pullRequest: ['number' => 15, 'title' => 'Add feature'],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === 15;
    });
});

it('passes null issue number for push events', function () {
    Queue::fake();

    $agent = Agent::factory()->create([
        'organization_id' => $this->organization->id,
        'events' => ['push'],
        'tools' => [],
    ]);
    $this->repo->agents()->attach($agent);

    event(new \App\Events\GitHubPushReceived(
        ref: 'refs/heads/main',
        before: 'aaa111',
        after: 'bbb222',
        commits: [['id' => 'bbb222', 'message' => 'Fix bug']],
        repository: ['full_name' => 'acme/widgets'],
        installationId: 12345,
    ));

    Queue::assertPushed(RunWebhookAgent::class, function ($job) {
        return $job->issueNumber === null;
    });
});
