<?php

use App\Ai\Tools\BashTool as AiBashTool;
use App\Services\LocalExecutionDriver;

beforeEach(function () {
    $rawTempDir = sys_get_temp_dir().'/bash-tool-test-'.uniqid();
    mkdir($rawTempDir, 0755, true);
    $this->tempDir = realpath($rawTempDir);
    $this->driver = new LocalExecutionDriver($rawTempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $this->driver->cleanup();
    }
});

describe('AI BashTool', function () {
    it('executes a command and returns stdout, stderr, and exit code', function () {
        $tool = new AiBashTool($this->driver);

        $request = new \Laravel\Ai\Tools\Request(['command' => 'echo "hello world"']);
        $response = $tool->handle($request);
        $decoded = json_decode($response, true);

        expect($decoded['stdout'])->toContain('hello world');
        expect($decoded)->not->toHaveKey('stderr');
        expect($decoded['exit_code'])->toBe(0);
    });

    it('captures stderr from failing commands', function () {
        $tool = new AiBashTool($this->driver);

        $request = new \Laravel\Ai\Tools\Request(['command' => 'echo "error output" >&2; exit 1']);
        $response = $tool->handle($request);
        $decoded = json_decode($response, true);

        expect($decoded['stderr'])->toContain('error output');
        expect($decoded['exit_code'])->toBe(1);
    });

    it('passes timeout to the driver', function () {
        $tool = new AiBashTool($this->driver);

        $request = new \Laravel\Ai\Tools\Request(['command' => 'echo "fast"', 'timeout' => 10]);
        $response = $tool->handle($request);
        $decoded = json_decode($response, true);

        expect($decoded['stdout'])->toContain('fast');
        expect($decoded['exit_code'])->toBe(0);
    });

    it('truncates large output', function () {
        $tool = new AiBashTool($this->driver);

        $reflection = new ReflectionMethod($tool, 'truncateOutput');
        $result = $reflection->invoke($tool, str_repeat('a', 200), 100);

        expect(strlen($result))->toBeLessThan(200);
        expect($result)->toContain('[Output truncated at 100 bytes]');
    });

    it('does not truncate output under the limit', function () {
        $tool = new AiBashTool($this->driver);

        $reflection = new ReflectionMethod($tool, 'truncateOutput');
        $input = str_repeat('a', 50);
        $result = $reflection->invoke($tool, $input, 100);

        expect($result)->toBe($input);
    });

    it('executes commands in the worktree directory', function () {
        $tool = new AiBashTool($this->driver);

        file_put_contents($this->tempDir.'/marker.txt', 'found');

        $request = new \Laravel\Ai\Tools\Request(['command' => 'cat marker.txt']);
        $response = $tool->handle($request);
        $decoded = json_decode($response, true);

        expect($decoded['stdout'])->toContain('found');
        expect($decoded['exit_code'])->toBe(0);
    });

    it('omits stdout key when stdout is empty', function () {
        $tool = new AiBashTool($this->driver);

        $request = new \Laravel\Ai\Tools\Request(['command' => 'true']);
        $response = $tool->handle($request);
        $decoded = json_decode($response, true);

        expect($decoded)->not->toHaveKey('stdout');
        expect($decoded)->not->toHaveKey('stderr');
        expect($decoded['exit_code'])->toBe(0);
    });
});
