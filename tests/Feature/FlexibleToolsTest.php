<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\CreateAgentTool;
use App\Ai\Tools\CreateIssueTool;
use App\Models\User;

it('includes flexible tools in availableForContext when no repo is selected', function () {
    $available = ToolRegistry::availableForContext(null);

    expect($available)
        ->toHaveKey('create_agent')
        ->toHaveKey('create_issue')
        ->toHaveKey('update_issue')
        ->toHaveKey('list_workspaces')
        ->toHaveKey('create_workspace');
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
        ->toHaveKey('get_issue')
        ->toHaveKey('list_issues')
        ->toHaveKey('create_pull_request')
        ->toHaveKey('create_workspace');
});

it('resolves create_agent as a local tool with a user', function () {
    $user = User::factory()->create();

    $tools = ToolRegistry::resolve(['create_agent'], null, $user);

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(CreateAgentTool::class);
});

it('resolves flexible tools without a repo context', function () {
    $tools = ToolRegistry::resolve(['create_issue', 'update_issue'], null);

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(CreateIssueTool::class);
});

it('does not require repo in create_agent schema', function () {
    $user = User::factory()->create();
    $tool = new CreateAgentTool($user);
    $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

    $fields = $tool->schema($schema);

    expect($fields)->toHaveKey('name')
        ->and($fields)->toHaveKey('workspace_ids');
});
