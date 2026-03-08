<?php

namespace App\Ai\Tools;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListReposTool implements Tool
{
    public function __construct(
        protected User $user,
    ) {}

    public function description(): string
    {
        return 'List repositories in the current organization.';
    }

    public function handle(Request $request): string
    {
        $repos = Repo::query()
            ->forCurrentOrganization($this->user)
            ->get(['id', 'name', 'source', 'source_reference', 'source_url']);

        return json_encode($repos, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
