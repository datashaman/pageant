<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class BashTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Execute a shell command in the worktree directory. Returns stdout, stderr, and exit code.';
    }

    public function handle(Request $request): string
    {
        $command = $request['command'];
        $timeout = $request['timeout'] ?? null;

        $result = $this->driver->exec($command, $timeout);

        $output = [];
        if ($result->stdout !== '') {
            $output['stdout'] = $this->truncateOutput($result->stdout);
        }
        if ($result->stderr !== '') {
            $output['stderr'] = $this->truncateOutput($result->stderr);
        }
        $output['exit_code'] = $result->exitCode;

        return json_encode($output, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The shell command to execute in the worktree directory.')
                ->required(),
            'timeout' => $schema->integer()
                ->description('Command timeout in seconds. Defaults to 300 (5 minutes).'),
        ];
    }

    protected function truncateOutput(string $output, int $maxLength = 1048576): string
    {
        if (strlen($output) <= $maxLength) {
            return $output;
        }

        return substr($output, 0, $maxLength)."\n\n[Output truncated at {$maxLength} bytes]";
    }
}
