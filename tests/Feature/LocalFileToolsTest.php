<?php

use App\Contracts\ExecutionDriver;
use App\Mcp\Servers\WorktreeServer;
use App\Mcp\Tools\EditFileTool;
use App\Mcp\Tools\GlobTool;
use App\Mcp\Tools\GrepTool;
use App\Mcp\Tools\ListDirectoryTool;
use App\Mcp\Tools\ReadFileTool;
use App\Mcp\Tools\WriteFileTool;
use App\Services\LocalExecutionDriver;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pageant-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $driver = new LocalExecutionDriver($this->tempDir);
    $this->app->instance(ExecutionDriver::class, $driver);

    file_put_contents($this->tempDir.'/hello.txt', "line one\nline two\nline three\nline four\nline five\n");
    mkdir($this->tempDir.'/src', 0755, true);
    file_put_contents($this->tempDir.'/src/app.php', "<?php\n\necho 'Hello World';\n");
    file_put_contents($this->tempDir.'/src/util.php', "<?php\n\nfunction helper() { return true; }\n");
    mkdir($this->tempDir.'/docs', 0755, true);
    file_put_contents($this->tempDir.'/docs/readme.md', "# Readme\n\nSome docs.\n");
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->tempDir);
    }
});

it('reads a file', function () {
    $response = WorktreeServer::tool(ReadFileTool::class, [
        'path' => 'hello.txt',
    ]);

    $response->assertOk()
        ->assertSee('line one')
        ->assertSee('line five');
});

it('reads a file with offset and limit', function () {
    $response = WorktreeServer::tool(ReadFileTool::class, [
        'path' => 'hello.txt',
        'offset' => 1,
        'limit' => 2,
    ]);

    $response->assertOk()
        ->assertSee('line two')
        ->assertSee('line three');
});

it('writes a new file', function () {
    $response = WorktreeServer::tool(WriteFileTool::class, [
        'path' => 'new-file.txt',
        'content' => 'Hello from test!',
    ]);

    $response->assertOk()
        ->assertSee('File written');

    expect(file_get_contents($this->tempDir.'/new-file.txt'))->toBe('Hello from test!');
});

it('overwrites an existing file', function () {
    $response = WorktreeServer::tool(WriteFileTool::class, [
        'path' => 'hello.txt',
        'content' => 'replaced content',
    ]);

    $response->assertOk();

    expect(file_get_contents($this->tempDir.'/hello.txt'))->toBe('replaced content');
});

it('edits a file with string replacement', function () {
    $response = WorktreeServer::tool(EditFileTool::class, [
        'path' => 'hello.txt',
        'old_string' => 'line two',
        'new_string' => 'line TWO',
    ]);

    $response->assertOk()
        ->assertSee('File edited');

    $content = file_get_contents($this->tempDir.'/hello.txt');

    expect($content)->toContain('line TWO')
        ->and($content)->not->toContain("line two\n");
});

it('edits a file with replace_all', function () {
    file_put_contents($this->tempDir.'/repeat.txt', "foo bar foo baz foo\n");

    $response = WorktreeServer::tool(EditFileTool::class, [
        'path' => 'repeat.txt',
        'old_string' => 'foo',
        'new_string' => 'qux',
        'replace_all' => true,
    ]);

    $response->assertOk();

    expect(file_get_contents($this->tempDir.'/repeat.txt'))->toBe("qux bar qux baz qux\n");
});

it('finds files with glob', function () {
    $response = WorktreeServer::tool(GlobTool::class, [
        'pattern' => '*.php',
        'path' => 'src',
    ]);

    $response->assertOk()
        ->assertSee('app.php')
        ->assertSee('util.php');
});

it('searches file contents with grep', function () {
    $response = WorktreeServer::tool(GrepTool::class, [
        'pattern' => 'Hello',
    ]);

    $response->assertOk()
        ->assertSee('app.php');
});

it('searches with grep using file type filter', function () {
    $response = WorktreeServer::tool(GrepTool::class, [
        'pattern' => 'Hello',
        'type' => 'php',
    ]);

    $response->assertOk()
        ->assertSee('app.php');
});

it('searches with grep using output_mode files_with_matches', function () {
    $response = WorktreeServer::tool(GrepTool::class, [
        'pattern' => 'function',
        'output_mode' => 'files_with_matches',
    ]);

    $response->assertOk()
        ->assertSee('util.php');
});

it('lists directory contents', function () {
    $response = WorktreeServer::tool(ListDirectoryTool::class, []);

    $response->assertOk()
        ->assertSee('hello.txt')
        ->assertSee('src')
        ->assertSee('docs');
});

it('lists subdirectory contents', function () {
    $response = WorktreeServer::tool(ListDirectoryTool::class, [
        'path' => 'src',
    ]);

    $response->assertOk()
        ->assertSee('app.php')
        ->assertSee('util.php');
});
