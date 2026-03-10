<?php

use App\Models\WorkItem;
use App\Services\WorktreeBrowser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->browser = new WorktreeBrowser;
    $this->workItem = WorkItem::factory()->create([
        'worktree_path' => null,
        'worktree_branch' => null,
    ]);
});

it('returns false for hasWorktree when no worktree path', function () {
    expect($this->browser->hasWorktree($this->workItem))->toBeFalse();
});

it('returns false for hasWorktree when path does not exist', function () {
    $this->workItem->worktree_path = '/nonexistent/path';

    expect($this->browser->hasWorktree($this->workItem))->toBeFalse();
});

it('returns true for hasWorktree when path exists', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);

    $this->workItem->worktree_path = $tmpDir;

    expect($this->browser->hasWorktree($this->workItem))->toBeTrue();

    File::deleteDirectory($tmpDir);
});

it('returns empty diff when no worktree', function () {
    $result = $this->browser->getDiff($this->workItem);

    expect($result)->toBe([
        'diff' => '',
        'stats' => ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0],
    ]);
});

it('returns empty changed files when no worktree', function () {
    expect($this->browser->getChangedFiles($this->workItem))->toBe([]);
});

it('returns empty file tree when no worktree', function () {
    expect($this->browser->getFileTree($this->workItem))->toBe([]);
});

it('returns null for file contents when no worktree', function () {
    expect($this->browser->getFileContents($this->workItem, 'test.php'))->toBeNull();
});

it('returns null for file contents with path traversal', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);

    $this->workItem->worktree_path = $tmpDir;

    expect($this->browser->getFileContents($this->workItem, '../../etc/passwd'))->toBeNull();

    File::deleteDirectory($tmpDir);
});

it('gets diff from worktree with base mode', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);

    $this->workItem->worktree_path = $tmpDir;

    Process::fake([
        'git rev-parse --verify origin/main *' => Process::result('abc123', '', 0),
        'git diff origin/main...HEAD' => Process::result('diff --git a/file.php', '', 0),
        'git diff origin/main...HEAD --stat' => Process::result(' 1 file changed, 5 insertions(+), 2 deletions(-)', '', 0),
    ]);

    $result = $this->browser->getDiff($this->workItem, 'base');

    expect(trim($result['diff']))->toBe('diff --git a/file.php')
        ->and($result['stats']['files_changed'])->toBe(1)
        ->and($result['stats']['insertions'])->toBe(5)
        ->and($result['stats']['deletions'])->toBe(2);

    File::deleteDirectory($tmpDir);
});

it('gets diff from worktree with local mode', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);

    $this->workItem->worktree_path = $tmpDir;

    Process::fake([
        'git diff HEAD' => Process::result('local diff output', '', 0),
        'git diff HEAD --stat' => Process::result(' 2 files changed, 10 insertions(+)', '', 0),
    ]);

    $result = $this->browser->getDiff($this->workItem, 'local');

    expect(trim($result['diff']))->toBe('local diff output')
        ->and($result['stats']['files_changed'])->toBe(2)
        ->and($result['stats']['insertions'])->toBe(10)
        ->and($result['stats']['deletions'])->toBe(0);

    File::deleteDirectory($tmpDir);
});

it('gets changed files from worktree', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);

    $this->workItem->worktree_path = $tmpDir;

    Process::fake([
        'git rev-parse --verify origin/main *' => Process::result('abc123', '', 0),
        'git diff --name-status origin/main...HEAD' => Process::result("M\tapp/Models/User.php\nA\tapp/Models/Post.php\nD\told-file.php", '', 0),
    ]);

    $result = $this->browser->getChangedFiles($this->workItem);

    expect($result)->toHaveCount(3)
        ->and($result[0])->toBe(['path' => 'app/Models/User.php', 'status' => 'modified'])
        ->and($result[1])->toBe(['path' => 'app/Models/Post.php', 'status' => 'added'])
        ->and($result[2])->toBe(['path' => 'old-file.php', 'status' => 'deleted']);

    File::deleteDirectory($tmpDir);
});

it('reads file contents from worktree', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);
    File::put($tmpDir.'/test.txt', 'Hello, World!');

    $this->workItem->worktree_path = $tmpDir;

    $result = $this->browser->getFileContents($this->workItem, 'test.txt');

    expect($result)->toBe('Hello, World!');

    File::deleteDirectory($tmpDir);
});

it('gets file tree from worktree', function () {
    $tmpDir = sys_get_temp_dir().'/worktree-browser-test-'.uniqid();
    File::ensureDirectoryExists($tmpDir);
    File::ensureDirectoryExists($tmpDir.'/src');
    File::put($tmpDir.'/README.md', '# Test');

    $this->workItem->worktree_path = $tmpDir;

    Process::fake([
        'git ls-tree --name-only HEAD *' => Process::result("README.md\nsrc", '', 0),
    ]);

    $result = $this->browser->getFileTree($this->workItem);

    expect($result)->toHaveCount(2)
        ->and($result[0]['type'])->toBe('directory')
        ->and($result[0]['name'])->toBe('src')
        ->and($result[1]['type'])->toBe('file')
        ->and($result[1]['name'])->toBe('README.md');

    File::deleteDirectory($tmpDir);
});
