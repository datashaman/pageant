<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDirectoryTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'List files and directories in the worktree.';
    }

    public function handle(Request $request): string
    {
        $entries = $this->driver->listDirectory($request['path'] ?? '.');

        return json_encode($entries, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The directory path relative to the worktree root. Defaults to the root directory.'),
        ];
    }
}
