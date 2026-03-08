<?php

namespace App\Ai\Tools;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteRepoTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'Delete a repository.';
    }

    public function handle(Request $request): string
    {
        $repo = Repo::query()
            ->forCurrentOrganization($this->user)
            ->findOrFail($request['id']);

        $name = $repo->name;
        $repo->delete();

        return "Repository '{$name}' deleted successfully.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The repository ID.')
                ->required(),
        ];
    }
}
