<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WriteFileTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Create or overwrite a file in the worktree.';
    }

    public function handle(Request $request): string
    {
        $this->driver->writeFile($request['path'], $request['content']);

        return "File written: {$request['path']}";
    }

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
