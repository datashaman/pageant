<?php

use App\Models\WorkItem;
use App\Services\LocalExecutionDriver;
use App\Services\WorktreeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->basePath = sys_get_temp_dir().'/pageant-test-'.uniqid();
    File::ensureDirectoryExists($this->basePath);
    $this->manager = new WorktreeManager($this->basePath);
});

afterEach(function () {
    if (File::isDirectory($this->basePath)) {
        File::deleteDirectory($this->basePath);
    }
});

it('provisions a worktree with the correct directory structure', function () {
    Process::fake([
        '*git clone*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        '*git worktree add*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $path = $this->manager->provision($workItem);

    $expectedSlug = 'acme--widgets';
    expect($path)->toBe("{$this->basePath}/{$workItem->organization_id}/{$expectedSlug}/{$workItem->id}");

    $workItem->refresh();
    expect($workItem->worktree_path)->toBe($path)
        ->and($workItem->worktree_branch)->toBe("pageant/{$workItem->id}");

    Process::assertRan(fn ($process) => str_contains($process->command, 'git clone --bare'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'git worktree add'));
});

it('clones via SSH URL with correct repo reference', function () {
    Process::fake([
        '*git clone*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        '*git worktree add*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'datashaman/pageant#10',
    ]);

    $this->manager->provision($workItem);

    Process::assertRan(fn ($process) => str_contains($process->command, 'git@github.com:datashaman/pageant.git'));
});

it('fetches instead of cloning when bare clone already exists', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $barePath = "{$this->basePath}/{$workItem->organization_id}/acme--widgets/.bare";
    File::ensureDirectoryExists($barePath);

    Process::fake([
        '*git fetch*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        '*git worktree add*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $this->manager->provision($workItem);

    Process::assertRan(fn ($process) => str_contains($process->command, 'git fetch --all'));
    Process::assertNotRan(fn ($process) => str_contains($process->command, 'git clone'));
});

it('resolves an existing worktree path', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'worktree_path' => $this->basePath.'/test-worktree',
    ]);

    File::ensureDirectoryExists($workItem->worktree_path);

    $result = $this->manager->resolve($workItem);

    expect($result)->toBe($workItem->worktree_path);
});

it('returns null when resolving a worktree that does not exist', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'worktree_path' => '/nonexistent/path',
    ]);

    $result = $this->manager->resolve($workItem);

    expect($result)->toBeNull();
});

it('returns null when resolving a work item without worktree_path', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $result = $this->manager->resolve($workItem);

    expect($result)->toBeNull();
});

it('provision or resolve returns existing path when worktree exists', function () {
    $worktreePath = $this->basePath.'/existing-worktree';
    File::ensureDirectoryExists($worktreePath);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'worktree_path' => $worktreePath,
        'worktree_branch' => 'pageant/test',
    ]);

    Process::fake();

    $result = $this->manager->provisionOrResolve($workItem);

    expect($result)->toBe($worktreePath);
    Process::assertNothingRan();
});

it('provision or resolve provisions when no worktree exists', function () {
    Process::fake([
        '*git clone*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        '*git worktree add*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $result = $this->manager->provisionOrResolve($workItem);

    expect($result)->not->toBeNull();
    Process::assertRan(fn ($process) => str_contains($process->command, 'git clone'));
});

it('cleans up a worktree and clears model attributes', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'worktree_path' => $this->basePath.'/test-worktree',
        'worktree_branch' => 'pageant/test',
    ]);

    File::ensureDirectoryExists($workItem->worktree_path);

    $barePath = "{$this->basePath}/{$workItem->organization_id}/acme--widgets/.bare";
    File::ensureDirectoryExists($barePath);

    Process::fake([
        '*git worktree remove*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    $this->manager->cleanup($workItem);

    $workItem->refresh();
    expect($workItem->worktree_path)->toBeNull()
        ->and($workItem->worktree_branch)->toBeNull();

    Process::assertRan(fn ($process) => str_contains($process->command, 'git worktree remove'));
});

it('handles cleanup when worktree path is null', function () {
    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    Process::fake();

    $this->manager->cleanup($workItem);

    Process::assertNothingRan();
});

it('creates an execution driver for a work item', function () {
    $worktreePath = $this->basePath.'/driver-worktree';
    File::ensureDirectoryExists($worktreePath);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
        'worktree_path' => $worktreePath,
        'worktree_branch' => 'pageant/test',
    ]);

    $driver = $this->manager->createDriver($workItem);

    expect($driver)->toBeInstanceOf(LocalExecutionDriver::class);
});

it('throws an exception for invalid source reference', function () {
    Process::fake();

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'invalid-ref',
    ]);

    $this->manager->provision($workItem);
})->throws(RuntimeException::class, 'Invalid source reference for worktree');

it('throws an exception when clone fails', function () {
    Process::fake([
        '*git clone*' => Process::result(output: '', errorOutput: 'fatal: repository not found', exitCode: 128),
    ]);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $this->manager->provision($workItem);
})->throws(RuntimeException::class, 'Failed to clone repository');

it('throws an exception when worktree creation fails', function () {
    Process::fake([
        '*git clone*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        '*git worktree add*' => Process::result(output: '', errorOutput: 'fatal: branch already exists', exitCode: 128),
    ]);

    $workItem = WorkItem::factory()->create([
        'source' => 'github',
        'source_reference' => 'acme/widgets#42',
    ]);

    $this->manager->provision($workItem);
})->throws(RuntimeException::class, 'Failed to create worktree');
