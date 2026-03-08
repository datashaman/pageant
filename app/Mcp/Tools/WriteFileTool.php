<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create or overwrite a file in the worktree.')]
#[IsIdempotent]
#[IsOpenWorld]
class WriteFileTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        $this->driver->writeFile($validated['path'], $validated['content']);

        return Response::text("File written: {$validated['path']}");
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
            'content' => $schema->string()
                ->description('The content to write to the file.')
                ->required(),
        ];
    }
}
