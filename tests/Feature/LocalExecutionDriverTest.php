<?php

use App\Contracts\ExecutionResult;
use App\Services\LocalExecutionDriver;

beforeEach(function () {
    $rawTempDir = sys_get_temp_dir().'/execution-driver-test-'.uniqid();
    mkdir($rawTempDir, 0755, true);
    $this->tempDir = realpath($rawTempDir);
    $this->driver = new LocalExecutionDriver($rawTempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $this->driver->cleanup();
    }
});

describe('exec', function () {
    it('executes a command and returns an ExecutionResult', function () {
        $result = $this->driver->exec('echo "hello world"');

        expect($result)->toBeInstanceOf(ExecutionResult::class);
        expect($result->stdout)->toContain('hello world');
        expect($result->stderr)->toBe('');
        expect($result->exitCode)->toBe(0);
        expect($result->isSuccessful())->toBeTrue();
    });

    it('captures stderr and exit code for failing commands', function () {
        $result = $this->driver->exec('ls /nonexistent-path-xyz 2>&1; exit 1');

        expect($result->exitCode)->toBe(1);
        expect($result->isSuccessful())->toBeFalse();
    });
});

describe('readFile', function () {
    it('reads a file with line numbers', function () {
        file_put_contents($this->tempDir.'/test.txt', "line one\nline two\nline three");

        $output = $this->driver->readFile('test.txt');

        expect($output)->toContain('1	line one');
        expect($output)->toContain('2	line two');
        expect($output)->toContain('3	line three');
    });

    it('reads a file with offset and limit', function () {
        file_put_contents($this->tempDir.'/test.txt', "line one\nline two\nline three\nline four");

        $output = $this->driver->readFile('test.txt', offset: 1, limit: 2);

        expect($output)->not->toContain('line one');
        expect($output)->toContain('2	line two');
        expect($output)->toContain('3	line three');
        expect($output)->not->toContain('line four');
    });

    it('throws when file does not exist', function () {
        $this->driver->readFile('nonexistent.txt');
    })->throws(RuntimeException::class, 'File not found');
});

describe('writeFile', function () {
    it('writes content to a file', function () {
        $this->driver->writeFile('output.txt', 'hello');

        expect(file_get_contents($this->tempDir.'/output.txt'))->toBe('hello');
    });

    it('creates parent directories if needed', function () {
        $this->driver->writeFile('nested/dir/file.txt', 'nested content');

        expect(file_get_contents($this->tempDir.'/nested/dir/file.txt'))->toBe('nested content');
    });
});

describe('editFile', function () {
    it('replaces a unique string in a file', function () {
        file_put_contents($this->tempDir.'/edit.txt', 'Hello World');

        $this->driver->editFile('edit.txt', 'World', 'PHP');

        expect(file_get_contents($this->tempDir.'/edit.txt'))->toBe('Hello PHP');
    });

    it('throws when multiple matches found without replaceAll', function () {
        file_put_contents($this->tempDir.'/edit.txt', 'foo bar foo');

        $this->driver->editFile('edit.txt', 'foo', 'baz');
    })->throws(RuntimeException::class, 'Multiple matches');

    it('replaces all matches when replaceAll is true', function () {
        file_put_contents($this->tempDir.'/edit.txt', 'foo bar foo');

        $this->driver->editFile('edit.txt', 'foo', 'baz', replaceAll: true);

        expect(file_get_contents($this->tempDir.'/edit.txt'))->toBe('baz bar baz');
    });

    it('throws when string is not found', function () {
        file_put_contents($this->tempDir.'/edit.txt', 'Hello World');

        $this->driver->editFile('edit.txt', 'nonexistent', 'replacement');
    })->throws(RuntimeException::class, 'String not found');
});

describe('path validation', function () {
    it('rejects absolute paths', function () {
        $this->driver->readFile('/etc/passwd');
    })->throws(InvalidArgumentException::class, 'Absolute paths are not allowed');

    it('rejects path traversal with dot-dot segments', function () {
        file_put_contents($this->tempDir.'/test.txt', 'safe');

        $this->driver->readFile('../../etc/passwd');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');

    it('rejects path traversal on write operations', function () {
        $this->driver->writeFile('../../etc/evil.txt', 'hacked');
    })->throws(InvalidArgumentException::class, 'Path traversal is not allowed');
});

describe('glob', function () {
    it('returns matching files as relative paths', function () {
        file_put_contents($this->tempDir.'/file1.php', '<?php');
        file_put_contents($this->tempDir.'/file2.php', '<?php');
        file_put_contents($this->tempDir.'/file3.txt', 'text');

        $results = $this->driver->glob('*.php');

        expect($results)->toHaveCount(2);
        expect($results)->toContain('file1.php');
        expect($results)->toContain('file2.php');
        expect($results)->not->toContain('file3.txt');
    });
});

describe('grep', function () {
    it('searches file contents and returns matches', function () {
        file_put_contents($this->tempDir.'/search.txt', "hello world\ngoodbye world\nhello again");

        $results = $this->driver->grep('hello', 'search.txt');

        expect($results)->toHaveCount(2);
        expect($results[0])->toContain('hello world');
        expect($results[1])->toContain('hello again');
    });

    it('returns files with matches in files_with_matches mode', function () {
        file_put_contents($this->tempDir.'/a.txt', 'match here');
        file_put_contents($this->tempDir.'/b.txt', 'no luck');

        $results = $this->driver->grep('match', options: ['output_mode' => 'files_with_matches']);

        expect($results)->toHaveCount(1);
        expect($results[0])->toContain('a.txt');
    });

    it('returns empty array when no matches found', function () {
        file_put_contents($this->tempDir.'/empty.txt', 'nothing here');

        $results = $this->driver->grep('nonexistent');

        expect($results)->toBeEmpty();
    });
});

describe('listDirectory', function () {
    it('lists files and directories with metadata', function () {
        file_put_contents($this->tempDir.'/file.txt', 'content');
        mkdir($this->tempDir.'/subdir');

        $results = $this->driver->listDirectory('.');

        expect($results)->toBeArray();

        $names = array_column($results, 'name');
        expect($names)->toContain('file.txt');
        expect($names)->toContain('subdir');

        $fileEntry = collect($results)->firstWhere('name', 'file.txt');
        expect($fileEntry['type'])->toBe('file');
        expect($fileEntry['size'])->toBe(7);

        $dirEntry = collect($results)->firstWhere('name', 'subdir');
        expect($dirEntry['type'])->toBe('directory');
    });

    it('throws when directory does not exist', function () {
        $this->driver->listDirectory('nonexistent');
    })->throws(RuntimeException::class, 'File not found');
});

describe('getBasePath', function () {
    it('returns the resolved base path', function () {
        expect($this->driver->getBasePath())->toBe($this->tempDir);
    });
});

describe('cleanup', function () {
    it('removes the base directory recursively', function () {
        file_put_contents($this->tempDir.'/file.txt', 'content');
        mkdir($this->tempDir.'/subdir');
        file_put_contents($this->tempDir.'/subdir/nested.txt', 'nested');

        $this->driver->cleanup();

        expect(is_dir($this->tempDir))->toBeFalse();
    });
});
