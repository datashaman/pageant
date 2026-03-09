<?php

namespace App\Mcp\Tools;

use App\Models\Skill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Import a skill from a public registry into the current organization.')]
#[IsOpenWorld]
class ImportRegistrySkillTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'registry' => 'required|string|in:mcp-registry,smithery',
            'source_reference' => 'required|string|max:255',
            'source_url' => 'nullable|string|url|max:255',
            'enabled' => 'nullable|boolean',
        ]);

        $user = auth()->user();

        if (! $user) {
            return Response::text(json_encode(['error' => 'Authentication required to import skills.']));
        }

        $organizationId = $user->currentOrganizationId()
            ?? $user->organizations()->first()?->id;

        if (! $organizationId) {
            return Response::text(json_encode(['error' => 'No organization found for the current user.']));
        }

        $existing = Skill::query()
            ->where('organization_id', $organizationId)
            ->where('name', $validated['name'])
            ->first();

        if ($existing) {
            return Response::text(json_encode([
                'error' => "A skill named \"{$validated['name']}\" already exists in this organization.",
                'existing_skill' => $existing->toArray(),
            ], JSON_PRETTY_PRINT));
        }

        $skill = Skill::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'enabled' => $validated['enabled'] ?? true,
            'source' => $validated['registry'],
            'source_reference' => $validated['source_reference'],
            'source_url' => $validated['source_url'] ?? '',
            'allowed_tools' => [],
            'provider' => 'anthropic',
            'model' => 'inherit',
            'context' => '',
            'argument_hint' => '',
            'license' => '',
            'path' => '',
        ]);

        return Response::text(json_encode([
            'message' => "Skill \"{$skill->name}\" imported successfully from {$validated['registry']}.",
            'skill' => $skill->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name for the imported skill.')
                ->required(),
            'description' => $schema->string()
                ->description('A description of what the skill does.'),
            'registry' => $schema->string()
                ->description('The registry the skill is from: "mcp-registry" or "smithery".')
                ->required(),
            'source_reference' => $schema->string()
                ->description('The unique identifier/name of the skill in the registry.')
                ->required(),
            'source_url' => $schema->string()
                ->description('URL to the skill in the registry.'),
            'enabled' => $schema->boolean()
                ->description('Whether the skill is enabled. Defaults to true.'),
        ];
    }
}
