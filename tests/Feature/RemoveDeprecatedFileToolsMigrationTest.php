<?php

use App\Models\Agent;

it('replaces deprecated tool names with new equivalents on up', function () {
    $agent = Agent::factory()->create([
        'tools' => ['get_file_contents', 'search_code', 'other_tool'],
    ]);

    $migration = require database_path('migrations/2026_03_08_072500_remove_deprecated_file_tools_from_agents.php');
    $migration->up();

    $agent->refresh();

    expect($agent->tools)
        ->toContain('read_file')
        ->toContain('grep')
        ->toContain('other_tool')
        ->not->toContain('get_file_contents')
        ->not->toContain('search_code');
});

it('does not duplicate new tool names if already present', function () {
    $agent = Agent::factory()->create([
        'tools' => ['get_file_contents', 'read_file', 'other_tool'],
    ]);

    $migration = require database_path('migrations/2026_03_08_072500_remove_deprecated_file_tools_from_agents.php');
    $migration->up();

    $agent->refresh();

    $readFileCount = count(array_filter($agent->tools, fn ($t) => $t === 'read_file'));

    expect($readFileCount)->toBe(1)
        ->and($agent->tools)->not->toContain('get_file_contents');
});

it('restores old tool names on down', function () {
    $agent = Agent::factory()->create([
        'tools' => ['read_file', 'grep', 'other_tool'],
    ]);

    $migration = require database_path('migrations/2026_03_08_072500_remove_deprecated_file_tools_from_agents.php');
    $migration->down();

    $agent->refresh();

    expect($agent->tools)
        ->toContain('get_file_contents')
        ->toContain('search_code')
        ->toContain('other_tool')
        ->not->toContain('read_file')
        ->not->toContain('grep');
});

it('skips agents with null tools on up', function () {
    $agent = Agent::factory()->create([
        'tools' => [],
    ]);

    $migration = require database_path('migrations/2026_03_08_072500_remove_deprecated_file_tools_from_agents.php');
    $migration->up();

    $agent->refresh();

    expect($agent->tools)->toBe([]);
});

it('does not modify agents without deprecated tools', function () {
    $agent = Agent::factory()->create([
        'tools' => ['other_tool', 'another_tool'],
    ]);

    $migration = require database_path('migrations/2026_03_08_072500_remove_deprecated_file_tools_from_agents.php');
    $migration->up();

    $agent->refresh();

    expect($agent->tools)->toBe(['other_tool', 'another_tool']);
});
