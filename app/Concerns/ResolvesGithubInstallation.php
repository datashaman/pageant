<?php

namespace App\Concerns;

use App\Models\GithubInstallation;
use App\Models\WorkspaceReference;

trait ResolvesGithubInstallation
{
    /**
     * Resolve a GitHub installation from a repo name, scoped to the current organization.
     *
     * @return array{WorkspaceReference, GithubInstallation}
     */
    protected function resolveInstallation(string $repo): array
    {
        $ref = WorkspaceReference::forGithubRepo($repo)->with('workspace')->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();

        return [$ref, $installation];
    }
}
