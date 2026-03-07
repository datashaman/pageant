<?php

use App\Mcp\Servers\PageantServer;
use App\Mcp\Tools\CreateWorkItemTool;
use App\Mcp\Tools\DeleteWorkItemTool;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Models\WorkItem;
use App\Services\GitHubService;
use Mockery\MockInterface;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->repo = Repo::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets',
    ]);
});

it('creates a work item from a GitHub issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->once()
            ->withArgs(function ($installation, $repo, $issueNumber) {
                return $repo === 'acme/widgets' && $issueNumber === 42;
            })
            ->andReturn([
                'number' => 42,
                'title' => 'Fix the widget',
                'body' => 'The widget is broken',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/42',
            ]);
    });

    $response = PageantServer::tool(CreateWorkItemTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
        'board_id' => 'backlog',
    ]);

    $response->assertOk()
        ->assertSee('Fix the widget');

    $workItem = WorkItem::where('source_reference', 'acme/widgets#42')->first();

    expect($workItem)->not->toBeNull()
        ->and($workItem->title)->toBe('Fix the widget')
        ->and($workItem->description)->toBe('The widget is broken')
        ->and($workItem->board_id)->toBe('backlog')
        ->and($workItem->source)->toBe('github')
        ->and($workItem->source_url)->toBe('https://github.com/acme/widgets/issues/42')
        ->and($workItem->organization_id)->toBe($this->organization->id);
});

it('deletes a work item by repo and issue number', function () {
    WorkItem::factory()->create([
        'organization_id' => $this->organization->id,
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'board_id' => 'backlog',
    ]);

    $response = PageantServer::tool(DeleteWorkItemTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
    ]);

    $response->assertOk()
        ->assertSee('deleted successfully');

    expect(WorkItem::where('source_reference', 'acme/widgets#42')->exists())->toBeFalse();
});
