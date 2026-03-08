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

#[Description('Search file contents using a regex pattern in the worktree.')]
#[IsReadOnly]
#[IsOpenWorld]
class GrepTool extends Tool
{
    public function __construct(
        protected ExecutionDriver $driver,
    ) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'pattern' => 'required|string',
            'path' => 'nullable|string',
            'type' => 'nullable|string',
            'context' => 'nullable|integer|min:0',
            'output_mode' => 'nullable|string|in:content,files_with_matches,count',
        ]);

        $options = [];

        if (isset($validated['type'])) {
            $options['type'] = $validated['type'];
        }

        if (isset($validated['context'])) {
            $options['context'] = $validated['context'];
        }

        if (isset($validated['output_mode'])) {
            $options['output_mode'] = $validated['output_mode'];
        }

        $results = $this->driver->grep(
            $validated['pattern'],
            $validated['path'] ?? null,
            $options,
        );

        return Response::text(implode("\n", $results));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
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
