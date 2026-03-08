<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class EditFileTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Perform exact string replacement in a file within the worktree.';
    }

    public function handle(Request $request): string
    {
        $this->driver->editFile(
            $request['path'],
            $request['old_string'],
            $request['new_string'],
            $request['replace_all'] ?? false,
        );

        return "File edited: {$request['path']}";
    }

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
