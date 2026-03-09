<?php

use App\Ai\ToolRegistry;
use App\Ai\Tools\BashTool;
use App\Ai\Tools\EditFileTool;
use App\Ai\Tools\GitCommitTool;
use App\Ai\Tools\GitDiffTool;
use App\Ai\Tools\GitLogTool;
use App\Ai\Tools\GitPushTool;
use App\Ai\Tools\GitStatusTool;
use App\Ai\Tools\GlobTool;
use App\Ai\Tools\GrepTool;
use App\Ai\Tools\ListDirectoryTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\WriteFileTool;
use App\Contracts\ExecutionDriver;

it('returns all worktree tool names', function () {
    $names = ToolRegistry::worktreeToolNames();

    expect($names)->toContain('read_file')
        ->toContain('write_file')
        ->toContain('edit_file')
        ->toContain('glob')
        ->toContain('grep')
        ->toContain('list_directory')
        ->toContain('bash')
        ->toContain('git_status')
        ->toContain('git_diff')
        ->toContain('git_commit')
        ->toContain('git_push')
        ->toContain('git_log');
});

it('resolves worktree tools when a driver is provided', function () {
    $driver = Mockery::mock(ExecutionDriver::class);

    $tools = ToolRegistry::resolve(['read_file', 'bash', 'git_status'], driver: $driver);

    expect($tools)->toHaveCount(3)
        ->and($tools[0])->toBeInstanceOf(ReadFileTool::class)
        ->and($tools[1])->toBeInstanceOf(BashTool::class)
        ->and($tools[2])->toBeInstanceOf(GitStatusTool::class);
});

it('skips worktree tools when no driver is provided', function () {
    $tools = ToolRegistry::resolve(['read_file', 'bash', 'git_status']);

    expect($tools)->toBeEmpty();
});

it('includes worktree category in groupedByCategory', function () {
    $categories = ToolRegistry::groupedByCategory();

    expect($categories)->toHaveKeys(['github', 'pageant', 'worktree'])
        ->and($categories['worktree'])->toHaveKeys(['Files', 'Commands', 'Git'])
        ->and($categories['worktree']['Files'])->toHaveKeys(['read_file', 'write_file', 'edit_file', 'glob', 'grep', 'list_directory'])
        ->and($categories['worktree']['Commands'])->toHaveKeys(['bash'])
        ->and($categories['worktree']['Git'])->toHaveKeys(['git_status', 'git_diff', 'git_commit', 'git_push', 'git_log']);
});

it('excludes worktree tools from githubToolNames', function () {
    $names = ToolRegistry::githubToolNames();

    expect($names)->not->toContain('read_file')
        ->not->toContain('bash')
        ->not->toContain('git_status');
});

it('includes worktree tools in availableForContext when hasWorktree is true', function () {
    $tools = ToolRegistry::availableForContext(hasWorktree: true);

    expect($tools)->toHaveKey('read_file')
        ->toHaveKey('bash')
        ->toHaveKey('git_status');
});

it('excludes worktree tools from availableForContext when hasWorktree is false', function () {
    $tools = ToolRegistry::availableForContext(hasWorktree: false);

    expect($tools)->not->toHaveKey('read_file')
        ->not->toHaveKey('bash')
        ->not->toHaveKey('git_status');
});

it('resolves all worktree tool types with a driver', function () {
    $driver = Mockery::mock(ExecutionDriver::class);
    $allWorktreeNames = ToolRegistry::worktreeToolNames();

    $tools = ToolRegistry::resolve($allWorktreeNames, driver: $driver);

    expect($tools)->toHaveCount(count($allWorktreeNames));

    $classes = array_map(fn ($tool) => $tool::class, $tools);

    expect($classes)->toContain(ReadFileTool::class)
        ->toContain(WriteFileTool::class)
        ->toContain(EditFileTool::class)
        ->toContain(GlobTool::class)
        ->toContain(GrepTool::class)
        ->toContain(ListDirectoryTool::class)
        ->toContain(BashTool::class)
        ->toContain(GitStatusTool::class)
        ->toContain(GitDiffTool::class)
        ->toContain(GitCommitTool::class)
        ->toContain(GitPushTool::class)
        ->toContain(GitLogTool::class);
});
