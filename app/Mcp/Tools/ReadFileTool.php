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

#[Description('Read file contents from the worktree, with optional line offset and limit for large files.')]
#[IsReadOnly]
#[IsOpenWorld]
class ReadFileTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'offset' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1',
        ]);

        $contents = $this->driver->readFile(
            $validated['path'],
            $validated['offset'] ?? null,
            $validated['limit'] ?? null,
        );

        return Response::text($contents);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The file path relative to the worktree root.')
                ->required(),
            'offset' => $schema->integer()
                ->description('Line number to start reading from (1-based).'),
            'limit' => $schema->integer()
                ->description('Maximum number of lines to read.'),
        ];
    }
}
