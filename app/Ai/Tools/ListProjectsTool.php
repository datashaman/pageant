<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListProjectsTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'List projects in the current organization.';
    }

    public function handle(Request $request): string
    {
        $projects = Project::query()
            ->forCurrentOrganization($this->user)
            ->get(['id', 'name', 'description']);

        return json_encode($projects, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
