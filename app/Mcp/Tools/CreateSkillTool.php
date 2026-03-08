<?php

namespace App\Mcp\Tools;

use App\Models\Skill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Create a new skill in the current organization.')]
#[IsOpenWorld]
class CreateSkillTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'argument_hint' => 'nullable|string',
            'license' => 'nullable|string',
            'enabled' => 'nullable|boolean',
            'path' => 'nullable|string',
            'allowed_tools' => 'nullable|array',
            'allowed_tools.*' => 'string',
            'provider' => 'nullable|string|in:anthropic,openai',
            'model' => 'nullable|string',
            'context' => 'nullable|string',
        ]);

        $user = auth()->user();

        if (! $user) {
            return Response::text(json_encode(['error' => 'Authentication required to create skills.']));
        }

        $organizationId = $user->currentOrganizationId()
            ?? $user->organizations()->first()?->id;

        if (! $organizationId) {
            return Response::text(json_encode(['error' => 'No organization found for the current user.']));
        }

        $skill = Skill::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'argument_hint' => $validated['argument_hint'] ?? '',
            'license' => $validated['license'] ?? '',
            'enabled' => $validated['enabled'] ?? true,
            'path' => $validated['path'] ?? '',
            'allowed_tools' => $validated['allowed_tools'] ?? [],
            'provider' => $validated['provider'] ?? 'anthropic',
            'model' => $validated['model'] ?? 'inherit',
            'context' => $validated['context'] ?? '',
            'source' => '',
            'source_reference' => '',
        ]);

        return Response::text(json_encode($skill->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The name of the skill.')
                ->required(),
            'description' => $schema->string()
                ->description('A description of what the skill does.'),
            'argument_hint' => $schema->string()
                ->description('A hint for the argument the skill accepts.'),
            'license' => $schema->string()
                ->description('The license of the skill (e.g. MIT, Apache-2.0).'),
            'enabled' => $schema->boolean()
                ->description('Whether the skill is enabled. Defaults to true.'),
            'path' => $schema->string()
                ->description('The file path to the skill definition.'),
            'allowed_tools' => $schema->array()
                ->items($schema->string())
                ->description('Tool names the skill is allowed to use.'),
            'provider' => $schema->string()
                ->description('AI provider: "anthropic" or "openai". Defaults to "anthropic".'),
            'model' => $schema->string()
                ->description('Model name or "inherit". Defaults to "inherit".'),
            'context' => $schema->string()
                ->description('Additional context or instructions for the skill.'),
        ];
    }
}
