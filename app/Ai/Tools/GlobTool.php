<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GlobTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Find files matching a glob pattern in the worktree.';
    }

    public function handle(Request $request): string
    {
        $path = $request['path'] ?? null;

        $pattern = $path
            ? $path.'/'.$request['pattern']
            : $request['pattern'];

        $matches = $this->driver->glob($pattern);

        return json_encode($matches, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()
                ->description('The glob pattern to match files against (e.g. "**/*.php").')
                ->required(),
            'path' => $schema->string()
                ->description('Optional subdirectory to search within.'),
        ];
    }
}
