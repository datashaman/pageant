<?php

use App\Mcp\Servers\GitHubServer;
use App\Mcp\Tools\AddLabelsToIssueTool;
use App\Mcp\Tools\CloseIssueTool;
use App\Mcp\Tools\CreateCommentTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\CreatePullRequestTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\ListIssueLabelsTool;
use App\Mcp\Tools\ListLabelsTool;
use App\Mcp\Tools\RemoveLabelFromIssueTool;
use App\Mcp\Tools\UpdateIssueTool;
use App\Mcp\Tools\UpdatePullRequestTool;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
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

it('lists labels on a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listLabels')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'bug', 'color' => 'd73a4a', 'description' => 'Something is broken'],
                ['id' => 2, 'name' => 'enhancement', 'color' => 'a2eeef', 'description' => null],
            ]);
    });

    $response = GitHubServer::tool(ListLabelsTool::class, [
        'repo' => 'acme/widgets',
    ]);

    $response->assertOk()
        ->assertSee('bug')
        ->assertSee('enhancement');
});

it('lists labels on a specific issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listIssueLabels')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'bug', 'color' => 'd73a4a', 'description' => null],
            ]);
    });

    $response = GitHubServer::tool(ListIssueLabelsTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
    ]);

    $response->assertOk()
        ->assertSee('bug');
});

it('adds labels to an issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('addLabelsToIssue')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'bug', 'color' => 'd73a4a', 'description' => null],
                ['id' => 2, 'name' => 'urgent', 'color' => 'ff0000', 'description' => null],
            ]);
    });

    $response = GitHubServer::tool(AddLabelsToIssueTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
        'labels' => ['bug', 'urgent'],
    ]);

    $response->assertOk()
        ->assertSee('bug')
        ->assertSee('urgent');
});

it('removes a label from an issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('removeLabelFromIssue')
            ->once();
    });

    $response = GitHubServer::tool(RemoveLabelFromIssueTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
        'label' => 'bug',
    ]);

    $response->assertOk()
        ->assertSee("Label 'bug' removed from issue #42");
});

it('creates a label on a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createLabel')
            ->once()
            ->andReturn([
                'id' => 3,
                'name' => 'priority',
                'color' => 'ff9900',
                'description' => 'High priority',
            ]);
    });

    $response = GitHubServer::tool(CreateLabelTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'priority',
        'color' => 'ff9900',
        'description' => 'High priority',
    ]);

    $response->assertOk()
        ->assertSee('priority')
        ->assertSee('ff9900');
});

it('deletes a label from a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('deleteLabel')
            ->once();
    });

    $response = GitHubServer::tool(DeleteLabelTool::class, [
        'repo' => 'acme/widgets',
        'name' => 'obsolete',
    ]);

    $response->assertOk()
        ->assertSee("Label 'obsolete' deleted from acme/widgets");
});

it('creates an issue on a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->withArgs(function ($installation, $repo, $data) {
                return $repo === 'acme/widgets'
                    && $data['title'] === 'Fix the widget'
                    && $data['body'] === 'The widget is broken'
                    && $data['labels'] === ['bug'];
            })
            ->andReturn([
                'number' => 99,
                'title' => 'Fix the widget',
                'body' => 'The widget is broken',
                'state' => 'open',
                'labels' => [['name' => 'bug']],
                'html_url' => 'https://github.com/acme/widgets/issues/99',
            ]);
    });

    $response = GitHubServer::tool(CreateIssueTool::class, [
        'repo' => 'acme/widgets',
        'title' => 'Fix the widget',
        'body' => 'The widget is broken',
        'labels' => ['bug'],
    ]);

    $response->assertOk()
        ->assertSee('Fix the widget')
        ->assertSee('99');
});

it('updates an existing issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('updateIssue')
            ->once()
            ->withArgs(function ($installation, $repo, $issueNumber, $data) {
                return $repo === 'acme/widgets'
                    && $issueNumber === 42
                    && $data['state'] === 'closed'
                    && $data['state_reason'] === 'completed';
            })
            ->andReturn([
                'number' => 42,
                'title' => 'Original title',
                'state' => 'closed',
                'state_reason' => 'completed',
                'html_url' => 'https://github.com/acme/widgets/issues/42',
            ]);
    });

    $response = GitHubServer::tool(UpdateIssueTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
        'state' => 'closed',
        'state_reason' => 'completed',
    ]);

    $response->assertOk()
        ->assertSee('closed')
        ->assertSee('completed');
});

it('closes an issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('updateIssue')
            ->once()
            ->withArgs(function ($installation, $repo, $issueNumber, $data) {
                return $repo === 'acme/widgets'
                    && $issueNumber === 7
                    && $data['state'] === 'closed'
                    && $data['state_reason'] === 'not_planned';
            })
            ->andReturn([
                'number' => 7,
                'title' => 'Won\'t fix',
                'state' => 'closed',
                'state_reason' => 'not_planned',
            ]);
    });

    $response = GitHubServer::tool(CloseIssueTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 7,
        'state_reason' => 'not_planned',
    ]);

    $response->assertOk()
        ->assertSee('closed')
        ->assertSee('not_planned');
});

it('creates a pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createPullRequest')
            ->once()
            ->withArgs(function ($installation, $repo, $data) {
                return $repo === 'acme/widgets'
                    && $data['title'] === 'Add feature'
                    && $data['head'] === 'feature-branch'
                    && $data['base'] === 'main';
            })
            ->andReturn([
                'number' => 10,
                'title' => 'Add feature',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/pull/10',
            ]);
    });

    $response = GitHubServer::tool(CreatePullRequestTool::class, [
        'repo' => 'acme/widgets',
        'title' => 'Add feature',
        'head' => 'feature-branch',
        'base' => 'main',
    ]);

    $response->assertOk()
        ->assertSee('Add feature')
        ->assertSee('10');
});

it('updates a pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('updatePullRequest')
            ->once()
            ->withArgs(function ($installation, $repo, $pullNumber, $data) {
                return $repo === 'acme/widgets'
                    && $pullNumber === 10
                    && $data['state'] === 'closed';
            })
            ->andReturn([
                'number' => 10,
                'title' => 'Add feature',
                'state' => 'closed',
                'html_url' => 'https://github.com/acme/widgets/pull/10',
            ]);
    });

    $response = GitHubServer::tool(UpdatePullRequestTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
        'state' => 'closed',
    ]);

    $response->assertOk()
        ->assertSee('closed');
});

it('creates a comment on an issue or pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createComment')
            ->once()
            ->withArgs(function ($installation, $repo, $issueNumber, $body) {
                return $repo === 'acme/widgets'
                    && $issueNumber === 42
                    && $body === 'This looks good!';
            })
            ->andReturn([
                'id' => 555,
                'body' => 'This looks good!',
                'html_url' => 'https://github.com/acme/widgets/issues/42#issuecomment-555',
                'user' => ['login' => 'bot'],
            ]);
    });

    $response = GitHubServer::tool(CreateCommentTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
        'body' => 'This looks good!',
    ]);

    $response->assertOk()
        ->assertSee('This looks good!')
        ->assertSee('555');
});

it('fails when repo is not tracked', function () {
    GitHubServer::tool(ListLabelsTool::class, [
        'repo' => 'unknown/repo',
    ]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
