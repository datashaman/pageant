<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadFileTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Read file contents from the worktree, with optional line offset and limit.';
    }

    public function handle(Request $request): string
    {
        return $this->driver->readFile(
            $request['path'],
            $request['offset'] ?? null,
            $request['limit'] ?? null,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('The file path relative to the worktree root.')
                ->required(),
            'offset' => $schema->integer()
                ->description('0-based line offset (0 = first line).'),
            'limit' => $schema->integer()
                ->description('Maximum number of lines to read.'),
        ];
    }
}
