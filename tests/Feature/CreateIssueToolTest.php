<?php

use App\Ai\Tools\CreateIssueTool;
use App\Models\GithubInstallation;
use App\Models\Organization;
use App\Models\Repo;
use App\Services\GitHubService;
use Laravel\Ai\Tools\Request;
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

it('includes work item hint in create issue response', function () {
    $github = $this->mock(GitHubService::class, function (MockInterface $mock) {
        $mock->shouldReceive('createIssue')
            ->once()
            ->andReturn([
                'number' => 99,
                'title' => 'Fix the widget',
                'state' => 'open',
                'html_url' => 'https://github.com/acme/widgets/issues/99',
            ]);
    });

    $tool = new CreateIssueTool($github, $this->installation, 'acme/widgets');

    $result = $tool->handle(new Request(['title' => 'Fix the widget']));

    expect($result)
        ->toContain('Fix the widget')
        ->toContain('99')
        ->toContain('create_work_item');
});
