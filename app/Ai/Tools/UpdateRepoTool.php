<?php

namespace App\Ai\Tools;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateRepoTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Update a repository name.';
    }

    public function handle(Request $request): string
    {
        $repo = Repo::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $repo->update(['name' => $request['name']]);

        return json_encode($repo->fresh(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The repository ID.')
                ->required(),
            'name' => $schema->string()
                ->description('The new name for the repository.')
                ->required(),
        ];
    }
}
