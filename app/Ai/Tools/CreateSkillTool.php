<?php

namespace App\Ai\Tools;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateSkillTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Create a new skill in the current organization.';
    }

    public function handle(Request $request): string
    {
        $organizationId = $this->user->currentOrganizationId()
            ?? $this->user->organizations()->first()?->id;

        if (! $organizationId) {
            return json_encode(['error' => 'No organization found for the current user.']);
        }

        $skill = Skill::create([
            'organization_id' => $organizationId,
            'name' => $request['name'],
            'description' => $request['description'] ?? '',
            'argument_hint' => $request['argument_hint'] ?? '',
            'license' => $request['license'] ?? '',
            'enabled' => $request['enabled'] ?? true,
            'path' => $request['path'] ?? '',
            'allowed_tools' => $request['allowed_tools'] ?? [],
            'provider' => $request['provider'] ?? 'anthropic',
            'model' => $request['model'] ?? 'inherit',
            'context' => $request['context'] ?? '',
            'source' => '',
            'source_reference' => '',
        ]);

        return json_encode($skill->toArray(), JSON_PRETTY_PRINT);
    }

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
