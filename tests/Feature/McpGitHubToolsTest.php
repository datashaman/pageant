<?php

use App\Mcp\Servers\GitHubServer;
use App\Mcp\Tools\AddLabelsToIssueTool;
use App\Mcp\Tools\CloseIssueTool;
use App\Mcp\Tools\CreateBranchTool;
use App\Mcp\Tools\CreateCommentTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\CreatePullRequestReviewTool;
use App\Mcp\Tools\CreatePullRequestTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\GetCommitStatusTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetPullRequestDiffTool;
use App\Mcp\Tools\GetPullRequestTool;
use App\Mcp\Tools\ListBranchesTool;
use App\Mcp\Tools\ListCheckRunsTool;
use App\Mcp\Tools\ListCommentsTool;
use App\Mcp\Tools\ListIssueLabelsTool;
use App\Mcp\Tools\ListIssuesTool;
use App\Mcp\Tools\ListLabelsTool;
use App\Mcp\Tools\ListPullRequestFilesTool;
use App\Mcp\Tools\ListPullRequestsTool;
use App\Mcp\Tools\MergePullRequestTool;
use App\Mcp\Tools\RemoveLabelFromIssueTool;
use App\Mcp\Tools\RequestReviewersTool;
use App\Mcp\Tools\SearchIssuesTool;
use App\Mcp\Tools\UpdateIssueTool;
use App\Mcp\Tools\UpdatePullRequestTool;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Workspace;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Mockery\MockInterface;

beforeEach(function () {
    $this->markTestSkipped('Requires Repo model - deferred to follow-up PR');
    $this->organization = Organization::factory()->create();
    $this->installation = GithubInstallation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->workspace = Workspace::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->workspaceReference = WorkspaceReference::factory()->create([
        'workspace_id' => $this->workspace->id,
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

it('lists branches on a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listBranches')
            ->once()
            ->andReturn([
                ['name' => 'main', 'commit' => ['sha' => 'abc123'], 'protected' => true],
                ['name' => 'develop', 'commit' => ['sha' => 'def456'], 'protected' => false],
            ]);
    });

    $response = GitHubServer::tool(ListBranchesTool::class, [
        'repo' => 'acme/widgets',
    ]);

    $response->assertOk()
        ->assertSee('main')
        ->assertSee('develop');
});

it('creates a branch on a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createBranch')
            ->once()
            ->withArgs(function ($installation, $repo, $branch, $sha) {
                return $repo === 'acme/widgets'
                    && $branch === 'feature/new-thing'
                    && $sha === 'abc123def456abc123def456abc123def456abc1';
            })
            ->andReturn([
                'ref' => 'refs/heads/feature/new-thing',
                'object' => ['sha' => 'abc123def456abc123def456abc123def456abc1', 'type' => 'commit'],
            ]);
    });

    $response = GitHubServer::tool(CreateBranchTool::class, [
        'repo' => 'acme/widgets',
        'branch' => 'feature/new-thing',
        'sha' => 'abc123def456abc123def456abc123def456abc1',
    ]);

    $response->assertOk()
        ->assertSee('feature')
        ->assertSee('new-thing');
});

it('gets a single issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getIssue')
            ->once()
            ->andReturn([
                'number' => 42,
                'title' => 'Fix the widget',
                'body' => 'It is broken',
                'state' => 'open',
                'labels' => [['name' => 'bug']],
            ]);
    });

    $response = GitHubServer::tool(GetIssueTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
    ]);

    $response->assertOk()
        ->assertSee('Fix the widget')
        ->assertSee('42');
});

it('lists open issues', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listIssues')
            ->once()
            ->andReturn([
                ['number' => 1, 'title' => 'First issue', 'state' => 'open'],
                ['number' => 2, 'title' => 'Second issue', 'state' => 'open'],
            ]);
    });

    $response = GitHubServer::tool(ListIssuesTool::class, [
        'repo' => 'acme/widgets',
    ]);

    $response->assertOk()
        ->assertSee('First issue')
        ->assertSee('Second issue');
});

it('gets a single pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getPullRequest')
            ->once()
            ->andReturn([
                'number' => 10,
                'title' => 'Add feature',
                'state' => 'open',
                'mergeable' => true,
                'head' => ['ref' => 'feature-branch'],
                'base' => ['ref' => 'main'],
            ]);
    });

    $response = GitHubServer::tool(GetPullRequestTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
    ]);

    $response->assertOk()
        ->assertSee('Add feature')
        ->assertSee('mergeable');
});

it('lists pull requests', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listPullRequests')
            ->once()
            ->andReturn([
                ['number' => 10, 'title' => 'Feature A', 'state' => 'open'],
                ['number' => 11, 'title' => 'Feature B', 'state' => 'open'],
            ]);
    });

    $response = GitHubServer::tool(ListPullRequestsTool::class, [
        'repo' => 'acme/widgets',
    ]);

    $response->assertOk()
        ->assertSee('Feature A')
        ->assertSee('Feature B');
});

it('lists comments on an issue', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listComments')
            ->once()
            ->andReturn([
                ['id' => 1, 'body' => 'First comment', 'user' => ['login' => 'alice']],
                ['id' => 2, 'body' => 'Second comment', 'user' => ['login' => 'bob']],
            ]);
    });

    $response = GitHubServer::tool(ListCommentsTool::class, [
        'repo' => 'acme/widgets',
        'issue_number' => 42,
    ]);

    $response->assertOk()
        ->assertSee('First comment')
        ->assertSee('Second comment');
});

it('merges a pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('mergePullRequest')
            ->once()
            ->andReturn([
                'sha' => 'merge-sha',
                'merged' => true,
                'message' => 'Pull Request successfully merged',
            ]);
    });

    $response = GitHubServer::tool(MergePullRequestTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
        'merge_method' => 'squash',
    ]);

    $response->assertOk()
        ->assertSee('merged');
});

it('lists pull request files', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listPullRequestFiles')
            ->once()
            ->andReturn([
                ['filename' => 'src/index.js', 'status' => 'modified', 'additions' => 5, 'deletions' => 2],
                ['filename' => 'tests/test.js', 'status' => 'added', 'additions' => 20, 'deletions' => 0],
            ]);
    });

    $response = GitHubServer::tool(ListPullRequestFilesTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
    ]);

    $response->assertOk()
        ->assertSee('index.js')
        ->assertSee('test.js');
});

it('gets a pull request diff', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getPullRequestDiff')
            ->once()
            ->andReturn("diff --git a/src/index.js b/src/index.js\n--- a/src/index.js\n+++ b/src/index.js\n@@ -1,3 +1,4 @@\n+import { Widget } from './widget';\n const app = new App();\n");
    });

    $response = GitHubServer::tool(GetPullRequestDiffTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
    ]);

    $response->assertOk()
        ->assertSee('diff --git')
        ->assertSee('Widget');
});

it('creates a pull request review with inline comments', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createPullRequestReview')
            ->once()
            ->withArgs(function ($installation, $repo, $pullNumber, $event, $body, $comments) {
                return $repo === 'acme/widgets'
                    && $pullNumber === 10
                    && $event === 'REQUEST_CHANGES'
                    && $body === 'A few issues'
                    && count($comments) === 1
                    && $comments[0]['path'] === 'src/index.js'
                    && $comments[0]['line'] === 5;
            })
            ->andReturn([
                'id' => 2,
                'state' => 'CHANGES_REQUESTED',
                'body' => 'A few issues',
            ]);
    });

    $response = GitHubServer::tool(CreatePullRequestReviewTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
        'event' => 'REQUEST_CHANGES',
        'body' => 'A few issues',
        'comments' => [
            ['path' => 'src/index.js', 'body' => 'This import is unused', 'line' => 5],
        ],
    ]);

    $response->assertOk()
        ->assertSee('CHANGES_REQUESTED');
});

it('requests reviewers on a pull request', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('requestReviewers')
            ->once()
            ->andReturn([
                'number' => 10,
                'requested_reviewers' => [['login' => 'alice']],
                'requested_teams' => [['slug' => 'core-team']],
            ]);
    });

    $response = GitHubServer::tool(RequestReviewersTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
        'reviewers' => ['alice'],
        'team_reviewers' => ['core-team'],
    ]);

    $response->assertOk()
        ->assertSee('alice')
        ->assertSee('core-team');
});

it('creates a pull request review', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createPullRequestReview')
            ->once()
            ->andReturn([
                'id' => 1,
                'state' => 'APPROVED',
                'body' => 'Looks good!',
            ]);
    });

    $response = GitHubServer::tool(CreatePullRequestReviewTool::class, [
        'repo' => 'acme/widgets',
        'pull_number' => 10,
        'event' => 'APPROVE',
        'body' => 'Looks good!',
    ]);

    $response->assertOk()
        ->assertSee('APPROVED');
});

it('gets commit status', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getCommitStatus')
            ->once()
            ->andReturn([
                'state' => 'success',
                'total_count' => 2,
                'statuses' => [
                    ['context' => 'ci/tests', 'state' => 'success'],
                    ['context' => 'ci/lint', 'state' => 'success'],
                ],
            ]);
    });

    $response = GitHubServer::tool(GetCommitStatusTool::class, [
        'repo' => 'acme/widgets',
        'ref' => 'main',
    ]);

    $response->assertOk()
        ->assertSee('success');
});

it('lists check runs', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('listCheckRuns')
            ->once()
            ->andReturn([
                'total_count' => 1,
                'check_runs' => [
                    ['id' => 1, 'name' => 'test-suite', 'status' => 'completed', 'conclusion' => 'success'],
                ],
            ]);
    });

    $response = GitHubServer::tool(ListCheckRunsTool::class, [
        'repo' => 'acme/widgets',
        'ref' => 'main',
    ]);

    $response->assertOk()
        ->assertSee('test-suite')
        ->assertSee('completed');
});

it('searches issues in a repository', function () {
    $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('searchIssues')
            ->once()
            ->withArgs(function ($installation, $query) {
                return str_contains($query, 'is:open label:bug') && str_contains($query, 'repo:acme/widgets');
            })
            ->andReturn([
                'total_count' => 1,
                'items' => [
                    ['number' => 42, 'title' => 'Widget broken'],
                ],
            ]);
    });

    $response = GitHubServer::tool(SearchIssuesTool::class, [
        'repo' => 'acme/widgets',
        'query' => 'is:open label:bug',
    ]);

    $response->assertOk()
        ->assertSee('Widget broken');
});

it('fails when repo is not tracked', function () {
    GitHubServer::tool(ListLabelsTool::class, [
        'repo' => 'unknown/repo',
    ]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
