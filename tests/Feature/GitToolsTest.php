<?php

use App\Ai\Tools\GitCommitTool;
use App\Ai\Tools\GitDiffTool;
use App\Ai\Tools\GitLogTool;
use App\Ai\Tools\GitStatusTool;
use App\Services\LocalExecutionDriver;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $rawTempDir = sys_get_temp_dir().'/git-tools-test-'.uniqid();
    mkdir($rawTempDir, 0755, true);
    $this->tempDir = realpath($rawTempDir);
    $this->driver = new LocalExecutionDriver($rawTempDir);

    $this->driver->exec('git init');
    $this->driver->exec('git config user.email "test@example.com"');
    $this->driver->exec('git config user.name "Test User"');

    file_put_contents($this->tempDir.'/README.md', 'Initial content');
    $this->driver->exec('git add -A');
    $this->driver->exec('git commit -m "Initial commit"');
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $this->driver->cleanup();
    }
});

describe('GitStatusTool', function () {
    it('shows clean status after commit', function () {
        $tool = new GitStatusTool($this->driver);
        $result = json_decode($tool->handle(new Request([])), true);

        expect($result['clean'])->toBeTrue();
        expect(trim($result['status']))->toBe('');
    });

    it('shows dirty status after modifying a file', function () {
        file_put_contents($this->tempDir.'/README.md', 'Modified content');

        $tool = new GitStatusTool($this->driver);
        $result = json_decode($tool->handle(new Request([])), true);

        expect($result['clean'])->toBeFalse();
        expect($result['status'])->toContain('README.md');
    });
});

describe('GitDiffTool', function () {
    it('rejects branch parameter starting with dash', function () {
        $tool = new GitDiffTool($this->driver);
        $result = json_decode($tool->handle(new Request(['branch' => '--exec=whoami'])), true);

        expect($result)->toHaveKey('error');
        expect($result['error'])->toContain('must not start with -');
    });

    it('shows changes after modifying a file', function () {
        file_put_contents($this->tempDir.'/README.md', 'Modified content');

        $tool = new GitDiffTool($this->driver);
        $result = json_decode($tool->handle(new Request([])), true);

        expect($result['empty'])->toBeFalse();
        expect($result['diff'])->toContain('Modified content');
    });

    it('shows staged changes with staged flag', function () {
        file_put_contents($this->tempDir.'/README.md', 'Staged content');
        $this->driver->exec('git add README.md');

        $tool = new GitDiffTool($this->driver);
        $result = json_decode($tool->handle(new Request(['staged' => true])), true);

        expect($result['empty'])->toBeFalse();
        expect($result['diff'])->toContain('Staged content');
    });

    it('shows empty diff when no changes', function () {
        $tool = new GitDiffTool($this->driver);
        $result = json_decode($tool->handle(new Request([])), true);

        expect($result['empty'])->toBeTrue();
    });
});

describe('GitCommitTool', function () {
    it('stages and commits all changes', function () {
        file_put_contents($this->tempDir.'/new-file.txt', 'New file content');

        $tool = new GitCommitTool($this->driver);
        $result = json_decode($tool->handle(new Request(['message' => 'Add new file'])), true);

        expect($result)->toHaveKey('hash');
        expect($result['hash'])->not->toBeEmpty();
        expect($result['summary'])->toContain('Add new file');

        $statusTool = new GitStatusTool($this->driver);
        $status = json_decode($statusTool->handle(new Request([])), true);
        expect($status['clean'])->toBeTrue();
    });

    it('stages only specified files', function () {
        file_put_contents($this->tempDir.'/file-a.txt', 'Content A');
        file_put_contents($this->tempDir.'/file-b.txt', 'Content B');

        $tool = new GitCommitTool($this->driver);
        $result = json_decode($tool->handle(new Request([
            'message' => 'Add file A only',
            'files' => ['file-a.txt'],
        ])), true);

        expect($result)->toHaveKey('hash');

        $statusTool = new GitStatusTool($this->driver);
        $status = json_decode($statusTool->handle(new Request([])), true);
        expect($status['status'])->toContain('file-b.txt');
    });
});

describe('GitLogTool', function () {
    it('rejects branch parameter starting with dash', function () {
        $tool = new GitLogTool($this->driver);
        $result = json_decode($tool->handle(new Request(['branch' => '--exec=whoami'])), true);

        expect($result)->toHaveKey('error');
        expect($result['error'])->toContain('must not start with -');
    });

    it('shows commit history', function () {
        $tool = new GitLogTool($this->driver);
        $result = json_decode($tool->handle(new Request([])), true);

        expect($result['count'])->toBeGreaterThanOrEqual(1);
        expect($result['commits'][0]['subject'])->toBe('Initial commit');
        expect($result['commits'][0]['author'])->toBe('Test User');
    });

    it('respects the limit parameter', function () {
        file_put_contents($this->tempDir.'/second.txt', 'Second');
        $this->driver->exec('git add -A && git commit -m "Second commit"');

        file_put_contents($this->tempDir.'/third.txt', 'Third');
        $this->driver->exec('git add -A && git commit -m "Third commit"');

        $tool = new GitLogTool($this->driver);
        $result = json_decode($tool->handle(new Request(['limit' => 2])), true);

        expect($result['count'])->toBe(2);
        expect($result['commits'][0]['subject'])->toBe('Third commit');
    });
});
