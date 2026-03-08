<?php

namespace App\Mcp\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Perform exact string replacement in a file within the worktree.')]
#[IsOpenWorld]
class EditFileTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'old_string' => 'required|string',
            'new_string' => 'required|string',
            'replace_all' => 'nullable|boolean',
        ]);

        $this->driver->editFile(
            $validated['path'],
            $validated['old_string'],
            $validated['new_string'],
            $validated['replace_all'] ?? false,
        );

        return Response::text("File edited: {$validated['path']}");
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
            'old_string' => $schema->string()
                ->description('The exact string to find and replace.')
                ->required(),
            'new_string' => $schema->string()
                ->description('The replacement string.')
                ->required(),
            'replace_all' => $schema->boolean()
                ->description('Replace all occurrences instead of requiring a unique match. Defaults to false.'),
        ];
    }
}
