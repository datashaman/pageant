<?php

namespace App\Ai\Tools;

use App\Contracts\ExecutionDriver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GrepTool implements Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function description(): string
    {
        return 'Search file contents using a regex pattern in the worktree.';
    }

    public function handle(Request $request): string
    {
        $options = [];

        if (isset($request['type'])) {
            $options['type'] = $request['type'];
        }

        if (isset($request['context'])) {
            $options['context'] = $request['context'];
        }

        if (isset($request['output_mode'])) {
            $options['output_mode'] = $request['output_mode'];
        }

        $results = $this->driver->grep(
            $request['pattern'],
            $request['path'] ?? null,
            $options,
        );

        return implode("\n", $results);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()
                ->description('The regex pattern to search for.')
                ->required(),
            'path' => $schema->string()
                ->description('Optional file or directory path to search within.'),
            'type' => $schema->string()
                ->description('File type filter (e.g. "php", "js", "ts").'),
            'context' => $schema->integer()
                ->description('Number of context lines to show before and after each match.'),
            'output_mode' => $schema->string()
                ->description('Output mode: "content" (default), "files_with_matches", or "count".'),
        ];
    }
}
