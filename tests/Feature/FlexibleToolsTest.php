<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\CloseWorkItemTool;
use App\Ai\Tools\CreateAgentTool;
use App\Ai\Tools\CreateWorkItemTool;
use App\Ai\Tools\ReopenWorkItemTool;
use App\Models\User;

it('includes flexible tools in availableForContext when no repo is selected', function () {
    $available = ToolRegistry::availableForContext(null);

    expect($available)
        ->toHaveKey('create_work_item')
        ->toHaveKey('close_work_item')
        ->toHaveKey('reopen_work_item')
        ->toHaveKey('create_agent')
        ->toHaveKey('create_issue')
        ->toHaveKey('update_issue');
});

it('excludes non-flexible github tools from availableForContext when no repo is selected', function () {
    $available = ToolRegistry::availableForContext(null);

    expect($available)
        ->not->toHaveKey('get_issue')
        ->not->toHaveKey('list_issues')
        ->not->toHaveKey('close_issue')
        ->not->toHaveKey('create_pull_request');
});

it('includes all tools in availableForContext when a repo is selected', function () {
    $available = ToolRegistry::availableForContext('acme/widgets');

    expect($available)
        ->toHaveKey('create_work_item')
        ->toHaveKey('get_issue')
        ->toHaveKey('list_issues')
        ->toHaveKey('create_pull_request');
});

it('resolves flexible tools without a repo context', function () {
    $tools = ToolRegistry::resolve(['create_work_item', 'close_work_item', 'reopen_work_item'], null);

    expect($tools)->toHaveCount(3)
        ->and($tools[0])->toBeInstanceOf(CreateWorkItemTool::class)
        ->and($tools[1])->toBeInstanceOf(CloseWorkItemTool::class)
        ->and($tools[2])->toBeInstanceOf(ReopenWorkItemTool::class);
});

it('resolves create_agent as a local tool with a user', function () {
    $user = User::factory()->create();

    $tools = ToolRegistry::resolve(['create_agent'], null, $user);

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(CreateAgentTool::class);
});

it('requires repo parameter in schema when no repo context is provided', function () {
    $tool = new CreateWorkItemTool(app(\App\Services\GitHubService::class));
    $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

    $fields = $tool->schema($schema);

    expect($fields)->toHaveKey('repo');
});

it('omits repo parameter in schema when repo context is provided', function () {
    $tool = new CreateWorkItemTool(
        app(\App\Services\GitHubService::class),
        null,
        'acme/widgets',
    );
    $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

    $fields = $tool->schema($schema);

    expect($fields)->not->toHaveKey('repo');
});

it('does not require repo in create_agent schema', function () {
    $user = User::factory()->create();
    $tool = new CreateAgentTool($user);
    $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

    $fields = $tool->schema($schema);

    expect($fields)->toHaveKey('repo')
        ->and($fields)->toHaveKey('name');
});
