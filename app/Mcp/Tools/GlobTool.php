<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Find files matching a glob pattern in the worktree.')]
#[IsReadOnly]
#[IsOpenWorld]
class GlobTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'pattern' => 'required|string',
            'path' => 'nullable|string',
        ]);

        $pattern = isset($validated['path'])
            ? $validated['path'].'/'.$validated['pattern']
            : $validated['pattern'];

        $matches = $this->driver->glob($pattern);

        return Response::text(json_encode($matches, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
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
