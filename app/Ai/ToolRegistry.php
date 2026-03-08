<?php

namespace App\Ai;

use App\Ai\Tools\AddLabelsToIssueTool;
use App\Ai\Tools\AttachRepoToProjectTool;
use App\Ai\Tools\CloseIssueTool;
use App\Ai\Tools\CreateAgentTool;
use App\Ai\Tools\CreateBranchTool;
use App\Ai\Tools\CreateCommentTool;
use App\Ai\Tools\CreateIssueTool;
use App\Ai\Tools\CreateLabelTool;
use App\Ai\Tools\CreateOrUpdateFileTool;
use App\Ai\Tools\CreateProjectTool;
use App\Ai\Tools\CreatePullRequestReviewTool;
use App\Ai\Tools\CreatePullRequestTool;
use App\Ai\Tools\CreateWorkItemTool;
use App\Ai\Tools\DeleteFileTool;
use App\Ai\Tools\DeleteLabelTool;
use App\Ai\Tools\DeleteProjectTool;
use App\Ai\Tools\DeleteRepoTool;
use App\Ai\Tools\DeleteWorkItemTool;
use App\Ai\Tools\DetachRepoFromProjectTool;
use App\Ai\Tools\GetCommitStatusTool;
use App\Ai\Tools\GetFileContentsTool;
use App\Ai\Tools\GetIssueTool;
use App\Ai\Tools\GetProjectTool;
use App\Ai\Tools\GetPullRequestDiffTool;
use App\Ai\Tools\GetPullRequestTool;
use App\Ai\Tools\GetRepositoryTreeTool;
use App\Ai\Tools\GetRepoTool;
use App\Ai\Tools\ListBranchesTool;
use App\Ai\Tools\ListCheckRunsTool;
use App\Ai\Tools\ListCommentsTool;
use App\Ai\Tools\ListIssueLabelsTool;
use App\Ai\Tools\ListIssuesTool;
use App\Ai\Tools\ListLabelsTool;
use App\Ai\Tools\ListProjectsTool;
use App\Ai\Tools\ListPullRequestFilesTool;
use App\Ai\Tools\ListPullRequestsTool;
use App\Ai\Tools\ListReposTool;
use App\Ai\Tools\MergePullRequestTool;
use App\Ai\Tools\RemoveLabelFromIssueTool;
use App\Ai\Tools\RequestReviewersTool;
use App\Ai\Tools\SearchCodeTool;
use App\Ai\Tools\SearchIssuesTool;
use App\Ai\Tools\UpdateIssueTool;
use App\Ai\Tools\UpdateProjectTool;
use App\Ai\Tools\UpdatePullRequestTool;
use App\Ai\Tools\UpdateRepoTool;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Models\User;
use App\Services\GitHubService;
use Laravel\Ai\Contracts\Tool;

class ToolRegistry
{
    /** @var array<string, array{class: class-string<Tool>, description: string, group: string}> */
    private const TOOL_MAP = [
        // Issues
        'get_issue' => ['class' => GetIssueTool::class, 'description' => 'Get an issue by number', 'group' => 'Issues'],
        'list_issues' => ['class' => ListIssuesTool::class, 'description' => 'List open issues', 'group' => 'Issues'],
        'create_issue' => ['class' => CreateIssueTool::class, 'description' => 'Create a new issue', 'group' => 'Issues'],
        'update_issue' => ['class' => UpdateIssueTool::class, 'description' => 'Update an issue', 'group' => 'Issues'],
        'close_issue' => ['class' => CloseIssueTool::class, 'description' => 'Close an issue', 'group' => 'Issues'],
        'search_issues' => ['class' => SearchIssuesTool::class, 'description' => 'Search issues and PRs', 'group' => 'Issues'],

        // Comments
        'list_comments' => ['class' => ListCommentsTool::class, 'description' => 'List comments on an issue or PR', 'group' => 'Comments'],
        'create_comment' => ['class' => CreateCommentTool::class, 'description' => 'Comment on an issue or PR', 'group' => 'Comments'],

        // Pull Requests
        'get_pull_request' => ['class' => GetPullRequestTool::class, 'description' => 'Get a PR by number', 'group' => 'Pull Requests'],
        'list_pull_requests' => ['class' => ListPullRequestsTool::class, 'description' => 'List pull requests', 'group' => 'Pull Requests'],
        'create_pull_request' => ['class' => CreatePullRequestTool::class, 'description' => 'Create a pull request', 'group' => 'Pull Requests'],
        'update_pull_request' => ['class' => UpdatePullRequestTool::class, 'description' => 'Update a pull request', 'group' => 'Pull Requests'],
        'merge_pull_request' => ['class' => MergePullRequestTool::class, 'description' => 'Merge a pull request', 'group' => 'Pull Requests'],
        'list_pull_request_files' => ['class' => ListPullRequestFilesTool::class, 'description' => 'List files changed in a PR', 'group' => 'Pull Requests'],
        'get_pull_request_diff' => ['class' => GetPullRequestDiffTool::class, 'description' => 'Get the unified diff for a PR', 'group' => 'Pull Requests'],
        'request_reviewers' => ['class' => RequestReviewersTool::class, 'description' => 'Request reviewers for a PR', 'group' => 'Pull Requests'],
        'create_pull_request_review' => ['class' => CreatePullRequestReviewTool::class, 'description' => 'Submit a PR review', 'group' => 'Pull Requests'],

        // Labels
        'list_labels' => ['class' => ListLabelsTool::class, 'description' => 'List all labels', 'group' => 'Labels'],
        'list_issue_labels' => ['class' => ListIssueLabelsTool::class, 'description' => 'List labels on an issue', 'group' => 'Labels'],
        'add_labels_to_issue' => ['class' => AddLabelsToIssueTool::class, 'description' => 'Add labels to an issue', 'group' => 'Labels'],
        'remove_label_from_issue' => ['class' => RemoveLabelFromIssueTool::class, 'description' => 'Remove a label from an issue', 'group' => 'Labels'],
        'create_label' => ['class' => CreateLabelTool::class, 'description' => 'Create a label', 'group' => 'Labels'],
        'delete_label' => ['class' => DeleteLabelTool::class, 'description' => 'Delete a label', 'group' => 'Labels'],

        // Branches
        'list_branches' => ['class' => ListBranchesTool::class, 'description' => 'List branches', 'group' => 'Branches'],
        'create_branch' => ['class' => CreateBranchTool::class, 'description' => 'Create a branch', 'group' => 'Branches'],

        // Files
        'get_file_contents' => ['class' => GetFileContentsTool::class, 'description' => 'Get file contents', 'group' => 'Files'],
        'get_repository_tree' => ['class' => GetRepositoryTreeTool::class, 'description' => 'List repository tree', 'group' => 'Files'],
        'create_or_update_file' => ['class' => CreateOrUpdateFileTool::class, 'description' => 'Create or update a file', 'group' => 'Files'],
        'delete_file' => ['class' => DeleteFileTool::class, 'description' => 'Delete a file', 'group' => 'Files'],
        'search_code' => ['class' => SearchCodeTool::class, 'description' => 'Search for code', 'group' => 'Files'],

        // CI / Status
        'get_commit_status' => ['class' => GetCommitStatusTool::class, 'description' => 'Get commit status', 'group' => 'CI / Status'],
        'list_check_runs' => ['class' => ListCheckRunsTool::class, 'description' => 'List check runs', 'group' => 'CI / Status'],

        // Work Items
        'create_work_item' => ['class' => CreateWorkItemTool::class, 'description' => 'Create a work item from an issue', 'group' => 'Work Items'],
        'delete_work_item' => ['class' => DeleteWorkItemTool::class, 'description' => 'Delete a work item', 'group' => 'Work Items'],

        // Agents
        'create_agent' => ['class' => CreateAgentTool::class, 'description' => 'Create a new agent', 'group' => 'Agents'],

        // Pageant — Repos (organization-scoped, no GitHub API needed)
        'list_repos' => ['class' => ListReposTool::class, 'description' => 'List repos in the current organization', 'group' => 'Pageant — Repos', 'local' => true],
        'get_repo' => ['class' => GetRepoTool::class, 'description' => 'Get a repo by ID', 'group' => 'Pageant — Repos', 'local' => true],
        'update_repo' => ['class' => UpdateRepoTool::class, 'description' => 'Update a repo name', 'group' => 'Pageant — Repos', 'local' => true],
        'delete_repo' => ['class' => DeleteRepoTool::class, 'description' => 'Delete a repo', 'group' => 'Pageant — Repos', 'local' => true],

        // Pageant — Projects
        'list_projects' => ['class' => ListProjectsTool::class, 'description' => 'List projects in the current organization', 'group' => 'Pageant — Projects', 'local' => true],
        'get_project' => ['class' => GetProjectTool::class, 'description' => 'Get a project by ID', 'group' => 'Pageant — Projects', 'local' => true],
        'create_project' => ['class' => CreateProjectTool::class, 'description' => 'Create a project', 'group' => 'Pageant — Projects', 'local' => true],
        'update_project' => ['class' => UpdateProjectTool::class, 'description' => 'Update a project', 'group' => 'Pageant — Projects', 'local' => true],
        'delete_project' => ['class' => DeleteProjectTool::class, 'description' => 'Delete a project', 'group' => 'Pageant — Projects', 'local' => true],
        'attach_repo_to_project' => ['class' => AttachRepoToProjectTool::class, 'description' => 'Attach a repo to a project', 'group' => 'Pageant — Projects', 'local' => true],
        'detach_repo_from_project' => ['class' => DetachRepoFromProjectTool::class, 'description' => 'Detach a repo from a project', 'group' => 'Pageant — Projects', 'local' => true],
    ];

    /**
     * @param  array<int, string>  $toolNames
     * @return Tool[]
     */
    public static function resolve(array $toolNames, ?string $repoFullName = null, ?User $user = null): array
    {
        if (empty($toolNames)) {
            return [];
        }

        $github = null;
        $installation = null;

        $hasGithubTools = collect($toolNames)->contains(
            fn (string $name) => isset(self::TOOL_MAP[$name]) && empty(self::TOOL_MAP[$name]['local'])
        );

        if ($hasGithubTools && $repoFullName) {
            $github = app(GitHubService::class);
            $repo = Repo::where('source', 'github')
                ->where('source_reference', $repoFullName)
                ->firstOrFail();
            $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();
        }

        $tools = [];

        foreach ($toolNames as $name) {
            if (! isset(self::TOOL_MAP[$name])) {
                continue;
            }

            $entry = self::TOOL_MAP[$name];

            if (! empty($entry['local'])) {
                if ($user) {
                    $tools[] = new ($entry['class'])($user);
                }
            } elseif ($github && $installation && $repoFullName) {
                $tools[] = new ($entry['class'])($github, $installation, $repoFullName);
            }
        }

        return $tools;
    }

    /**
     * @return array<string, string>
     */
    public static function availableForContext(?string $repoFullName = null): array
    {
        return array_filter(
            self::available(),
            fn (string $description, string $name) => ! empty(self::TOOL_MAP[$name]['local']) || $repoFullName !== null,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function available(): array
    {
        return array_map(fn (array $entry) => $entry['description'], self::TOOL_MAP);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::TOOL_MAP as $name => $entry) {
            $groups[$entry['group']][$name] = $entry['description'];
        }

        return $groups;
    }
}
