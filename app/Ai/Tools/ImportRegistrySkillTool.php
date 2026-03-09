<?php

namespace App\Ai\Tools;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ImportRegistrySkillTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Import a skill from a public registry into the current organization.';
    }

    public function handle(Request $request): string
    {
        $organizationId = $this->user->currentOrganizationId()
            ?? $this->user->organizations()->first()?->id;

        if (! $organizationId) {
            return json_encode(['error' => 'No organization found for the current user.']);
        }

        $name = $request['name'];

        $existing = Skill::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return json_encode([
                'error' => "A skill named \"{$name}\" already exists in this organization.",
                'existing_skill' => $existing->toArray(),
            ], JSON_PRETTY_PRINT);
        }

        $skill = Skill::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'description' => $request['description'] ?? '',
            'enabled' => $request['enabled'] ?? true,
            'source' => $request['registry'],
            'source_reference' => $request['source_reference'],
            'source_url' => $request['source_url'] ?? '',
            'allowed_tools' => [],
            'provider' => 'anthropic',
            'model' => 'inherit',
            'context' => '',
            'argument_hint' => '',
            'license' => '',
            'path' => '',
        ]);

        return json_encode([
            'message' => "Skill \"{$skill->name}\" imported successfully from {$request['registry']}.",
            'skill' => $skill->toArray(),
        ], JSON_PRETTY_PRINT);
    }

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
