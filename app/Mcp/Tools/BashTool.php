<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Execute a shell command in the worktree directory. Returns stdout, stderr, and exit code.')]
#[IsOpenWorld]
class BashTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'command' => 'required|string',
            'timeout' => 'nullable|integer|min:1',
        ]);

        $result = $this->driver->exec(
            $validated['command'],
            $validated['timeout'] ?? null,
        );

        $output = [];
        if ($result->stdout !== '') {
            $output['stdout'] = $this->truncateOutput($result->stdout);
        }
        if ($result->stderr !== '') {
            $output['stderr'] = $this->truncateOutput($result->stderr);
        }
        $output['exit_code'] = $result->exitCode;

        return Response::text(json_encode($output, JSON_PRETTY_PRINT));
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
