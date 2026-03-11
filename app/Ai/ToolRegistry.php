<?php

namespace App\Ai;

use App\Ai\Tools\AddLabelsToIssueTool;
use App\Ai\Tools\AddPlanStepTool;
use App\Ai\Tools\AddWorkspaceReferenceTool;
use App\Ai\Tools\ApprovePlanTool;
use App\Ai\Tools\AttachSkillToAgentTool;
use App\Ai\Tools\BashTool;
use App\Ai\Tools\CancelPlanTool;
use App\Ai\Tools\CloseIssueTool;
use App\Ai\Tools\CloseWorkspaceIssueTool;
use App\Ai\Tools\CreateAgentTool;
use App\Ai\Tools\CreateBranchTool;
use App\Ai\Tools\CreateCommentTool;
use App\Ai\Tools\CreateIssueTool;
use App\Ai\Tools\CreateLabelTool;
use App\Ai\Tools\CreatePlanTool;
use App\Ai\Tools\CreatePullRequestReviewTool;
use App\Ai\Tools\CreatePullRequestTool;
use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\CreateWorkspaceIssueTool;
use App\Ai\Tools\CreateWorkspaceTool;
use App\Ai\Tools\DeleteLabelTool;
use App\Ai\Tools\DeleteWorkspaceTool;
use App\Ai\Tools\EditFileTool;
use App\Ai\Tools\GetCommitStatusTool;
use App\Ai\Tools\GetIssueTool;
use App\Ai\Tools\GetPlanTool;
use App\Ai\Tools\GetPullRequestDiffTool;
use App\Ai\Tools\GetPullRequestTool;
use App\Ai\Tools\GetWorkspaceTool;
use App\Ai\Tools\GitCommitTool;
use App\Ai\Tools\GitDiffTool;
use App\Ai\Tools\GitLogTool;
use App\Ai\Tools\GitPushTool;
use App\Ai\Tools\GitStatusTool;
use App\Ai\Tools\GlobTool;
use App\Ai\Tools\GrepTool;
use App\Ai\Tools\ImportRegistrySkillTool;
use App\Ai\Tools\ListAgentsTool;
use App\Ai\Tools\ListBranchesTool;
use App\Ai\Tools\ListCheckRunsTool;
use App\Ai\Tools\ListCommentsTool;
use App\Ai\Tools\ListDirectoryTool;
use App\Ai\Tools\ListIssueLabelsTool;
use App\Ai\Tools\ListIssuesTool;
use App\Ai\Tools\ListLabelsTool;
use App\Ai\Tools\ListPlansTool;
use App\Ai\Tools\ListPullRequestFilesTool;
use App\Ai\Tools\ListPullRequestsTool;
use App\Ai\Tools\ListSkillsTool;
use App\Ai\Tools\ListWorkspaceReferencesTool;
use App\Ai\Tools\ListWorkspacesTool;
use App\Ai\Tools\MergePullRequestTool;
use App\Ai\Tools\PausePlanTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\RemoveLabelFromIssueTool;
use App\Ai\Tools\RemoveWorkspaceIssueTool;
use App\Ai\Tools\RemoveWorkspaceReferenceTool;
use App\Ai\Tools\ReopenWorkspaceIssueTool;
use App\Ai\Tools\RequestReviewersTool;
use App\Ai\Tools\ResumePlanTool;
use App\Ai\Tools\SearchAgentsTool;
use App\Ai\Tools\SearchIssuesTool;
use App\Ai\Tools\SearchRegistrySkillsTool;
use App\Ai\Tools\SearchSkillsTool;
use App\Ai\Tools\UpdateIssueTool;
use App\Ai\Tools\UpdatePullRequestTool;
use App\Ai\Tools\UpdateWorkspaceTool;
use App\Ai\Tools\WriteFileTool;
use App\Contracts\ExecutionDriver;
use App\Models\GithubInstallation;
use App\Models\User;
use App\Models\WorkspaceReference;
use App\Services\GitHubService;
use Laravel\Ai\Contracts\Tool;

class ToolRegistry
{
    /** @var array<string, array{class: class-string<Tool>, description: string, group: string}> */
    private const TOOL_MAP = [
        // Issues
        'get_issue' => ['class' => GetIssueTool::class, 'description' => 'Get an issue by number', 'group' => 'Issues'],
        'list_issues' => ['class' => ListIssuesTool::class, 'description' => 'List open issues', 'group' => 'Issues'],
        'create_issue' => ['class' => CreateIssueTool::class, 'description' => 'Create a new issue', 'group' => 'Issues', 'flexible' => true],
        'update_issue' => ['class' => UpdateIssueTool::class, 'description' => 'Update an issue', 'group' => 'Issues', 'flexible' => true],
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

        // CI / Status
        'get_commit_status' => ['class' => GetCommitStatusTool::class, 'description' => 'Get commit status', 'group' => 'CI / Status'],
        'list_check_runs' => ['class' => ListCheckRunsTool::class, 'description' => 'List check runs', 'group' => 'CI / Status'],

        // Workspace Issues
        'create_workspace_issue' => ['class' => CreateWorkspaceIssueTool::class, 'description' => 'Create a GitHub issue and add it to a workspace', 'group' => 'Workspace Issues', 'flexible' => true],
        'close_workspace_issue' => ['class' => CloseWorkspaceIssueTool::class, 'description' => 'Close a GitHub issue in a workspace', 'group' => 'Workspace Issues', 'flexible' => true],
        'reopen_workspace_issue' => ['class' => ReopenWorkspaceIssueTool::class, 'description' => 'Reopen a GitHub issue in a workspace', 'group' => 'Workspace Issues', 'flexible' => true],
        'remove_workspace_issue' => ['class' => RemoveWorkspaceIssueTool::class, 'description' => 'Remove an issue reference from a workspace', 'group' => 'Workspace Issues', 'local' => true],

        // Agents
        'create_agent' => ['class' => CreateAgentTool::class, 'description' => 'Create a new agent', 'group' => 'Agents', 'local' => true],
        'list_agents' => ['class' => ListAgentsTool::class, 'description' => 'List agents in the organization', 'group' => 'Agents', 'local' => true],
        'search_agents' => ['class' => SearchAgentsTool::class, 'description' => 'Search for agents by capability', 'group' => 'Agents', 'local' => true],

        // Skills
        'list_skills' => ['class' => ListSkillsTool::class, 'description' => 'List skills in the organization', 'group' => 'Skills', 'local' => true],
        'search_skills' => ['class' => SearchSkillsTool::class, 'description' => 'Search skills by capability', 'group' => 'Skills', 'local' => true],
        'create_skill' => ['class' => CreateSkillTool::class, 'description' => 'Create a new skill', 'group' => 'Skills', 'local' => true],
        'attach_skill_to_agent' => ['class' => AttachSkillToAgentTool::class, 'description' => 'Attach a skill to an agent', 'group' => 'Skills', 'local' => true],
        'search_registry_skills' => ['class' => SearchRegistrySkillsTool::class, 'description' => 'Search public registries for skills to import', 'group' => 'Skills', 'local' => true],
        'import_registry_skill' => ['class' => ImportRegistrySkillTool::class, 'description' => 'Import a skill from a public registry', 'group' => 'Skills', 'local' => true],

        // Plans
        'create_plan' => ['class' => CreatePlanTool::class, 'description' => 'Create an execution plan for a workspace', 'group' => 'Plans', 'local' => true],
        'get_plan' => ['class' => GetPlanTool::class, 'description' => 'Get a plan with its steps', 'group' => 'Plans', 'local' => true],
        'list_plans' => ['class' => ListPlansTool::class, 'description' => 'List plans for workspaces', 'group' => 'Plans', 'local' => true],
        'approve_plan' => ['class' => ApprovePlanTool::class, 'description' => 'Approve a pending plan for execution', 'group' => 'Plans', 'local' => true],
        'cancel_plan' => ['class' => CancelPlanTool::class, 'description' => 'Cancel a pending or running plan', 'group' => 'Plans', 'local' => true],
        'add_plan_step' => ['class' => AddPlanStepTool::class, 'description' => 'Add a step to an existing plan', 'group' => 'Plans', 'local' => true],
        'pause_plan' => ['class' => PausePlanTool::class, 'description' => 'Pause a running plan', 'group' => 'Plans', 'local' => true],
        'resume_plan' => ['class' => ResumePlanTool::class, 'description' => 'Resume a paused plan', 'group' => 'Plans', 'local' => true],

        // Workspace References
        'list_workspace_references' => ['class' => ListWorkspaceReferencesTool::class, 'description' => 'List references in a workspace', 'group' => 'Workspace References', 'local' => true],
        'add_workspace_reference' => ['class' => AddWorkspaceReferenceTool::class, 'description' => 'Add a source reference to a workspace', 'group' => 'Workspace References', 'local' => true],
        'remove_workspace_reference' => ['class' => RemoveWorkspaceReferenceTool::class, 'description' => 'Remove a reference from a workspace', 'group' => 'Workspace References', 'local' => true],

        // Workspaces
        'list_workspaces' => ['class' => ListWorkspacesTool::class, 'description' => 'List workspaces in the organization', 'group' => 'Workspaces', 'local' => true],
        'get_workspace' => ['class' => GetWorkspaceTool::class, 'description' => 'Get a workspace by ID', 'group' => 'Workspaces', 'local' => true],
        'create_workspace' => ['class' => CreateWorkspaceTool::class, 'description' => 'Create a workspace', 'group' => 'Workspaces', 'local' => true],
        'update_workspace' => ['class' => UpdateWorkspaceTool::class, 'description' => 'Update a workspace', 'group' => 'Workspaces', 'local' => true],
        'delete_workspace' => ['class' => DeleteWorkspaceTool::class, 'description' => 'Delete a workspace', 'group' => 'Workspaces', 'local' => true],

        // Worktree - Files
        'read_file' => ['class' => ReadFileTool::class, 'description' => 'Read file contents from the worktree', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],
        'write_file' => ['class' => WriteFileTool::class, 'description' => 'Create or overwrite a file in the worktree', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],
        'edit_file' => ['class' => EditFileTool::class, 'description' => 'Edit a file using exact string replacement', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],
        'glob' => ['class' => GlobTool::class, 'description' => 'Find files by glob pattern', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],
        'grep' => ['class' => GrepTool::class, 'description' => 'Search file contents with regex', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],
        'list_directory' => ['class' => ListDirectoryTool::class, 'description' => 'List files and directories', 'group' => 'Files', 'worktree' => true, 'category' => 'worktree'],

        // Worktree - Commands
        'bash' => ['class' => BashTool::class, 'description' => 'Execute a shell command in the worktree', 'group' => 'Commands', 'worktree' => true, 'category' => 'worktree'],

        // Worktree - Git
        'git_status' => ['class' => GitStatusTool::class, 'description' => 'Show working tree status', 'group' => 'Git', 'worktree' => true, 'category' => 'worktree'],
        'git_diff' => ['class' => GitDiffTool::class, 'description' => 'Show changes in the worktree', 'group' => 'Git', 'worktree' => true, 'category' => 'worktree'],
        'git_commit' => ['class' => GitCommitTool::class, 'description' => 'Stage and commit changes', 'group' => 'Git', 'worktree' => true, 'category' => 'worktree'],
        'git_push' => ['class' => GitPushTool::class, 'description' => 'Push commits to remote', 'group' => 'Git', 'worktree' => true, 'category' => 'worktree'],
        'git_log' => ['class' => GitLogTool::class, 'description' => 'View commit history', 'group' => 'Git', 'worktree' => true, 'category' => 'worktree'],
    ];

    /**
     * @param  array<int, string>  $toolNames
     * @return Tool[]
     */
    public static function resolve(array $toolNames, ?string $repoFullName = null, ?User $user = null, ?ExecutionDriver $driver = null): array
    {
        if (empty($toolNames)) {
            return [];
        }

        $github = null;
        $installation = null;

        $hasGithubTools = collect($toolNames)->contains(
            fn (string $name) => isset(self::TOOL_MAP[$name]) && empty(self::TOOL_MAP[$name]['local']) && empty(self::TOOL_MAP[$name]['worktree'])
        );

        if ($hasGithubTools && $repoFullName) {
            $github = app(GitHubService::class);
            $query = WorkspaceReference::where('source', 'github')
                ->where(function ($q) use ($repoFullName) {
                    $q->where('source_reference', $repoFullName)
                        ->orWhere('source_reference', 'LIKE', $repoFullName.'#%');
                });

            if ($user) {
                $query->whereHas('workspace', fn ($q) => $q->where('organization_id', $user->currentOrganizationId()));
            }

            $ref = $query->with('workspace')->firstOrFail();
            $installation = GithubInstallation::where('organization_id', $ref->workspace->organization_id)->firstOrFail();
        }

        $tools = [];

        foreach ($toolNames as $name) {
            if (! isset(self::TOOL_MAP[$name])) {
                continue;
            }

            $entry = self::TOOL_MAP[$name];

            if (! empty($entry['worktree'])) {
                if ($driver) {
                    $tools[] = new ($entry['class'])($driver);
                }
            } elseif (! empty($entry['local'])) {
                if ($user) {
                    $tools[] = new ($entry['class'])($user);
                }
            } elseif (! empty($entry['flexible'])) {
                $tools[] = new ($entry['class'])(
                    $github ?? app(GitHubService::class),
                    $installation,
                    $repoFullName,
                );
            } elseif ($github && $installation && $repoFullName) {
                $tools[] = new ($entry['class'])($github, $installation, $repoFullName);
            }
        }

        return $tools;
    }

    /**
     * @return array<string, string>
     */
    public static function availableForContext(?string $repoFullName = null, bool $hasWorktree = false): array
    {
        return array_filter(
            self::available(),
            fn (string $description, string $name) => match (true) {
                ! empty(self::TOOL_MAP[$name]['worktree']) => $hasWorktree,
                ! empty(self::TOOL_MAP[$name]['local']),
                ! empty(self::TOOL_MAP[$name]['flexible']) => true,
                default => $repoFullName !== null,
            },
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
     * @return list<string>
     */
    public static function githubToolNames(): array
    {
        return array_keys(array_filter(
            self::TOOL_MAP,
            fn (array $entry) => empty($entry['local']) && ($entry['category'] ?? null) !== 'pageant' && empty($entry['worktree']),
        ));
    }

    /**
     * @return list<string>
     */
    public static function pageantToolNames(): array
    {
        return array_keys(array_filter(
            self::TOOL_MAP,
            fn (array $entry) => ! empty($entry['local']) || ($entry['category'] ?? null) === 'pageant',
        ));
    }

    /**
     * @return list<string>
     */
    public static function worktreeToolNames(): array
    {
        return array_keys(array_filter(
            self::TOOL_MAP,
            fn (array $entry) => ! empty($entry['worktree']),
        ));
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

    /**
     * @return array{github: array<string, array<string, string>>, pageant: array<string, array<string, string>>, worktree: array<string, array<string, string>>}
     */
    public static function groupedByCategory(): array
    {
        $github = [];
        $pageant = [];
        $worktree = [];

        foreach (self::TOOL_MAP as $name => $entry) {
            if (! empty($entry['worktree'])) {
                $worktree[$entry['group']][$name] = $entry['description'];
            } elseif (! empty($entry['local']) || ($entry['category'] ?? null) === 'pageant') {
                $pageant[$entry['group']][$name] = $entry['description'];
            } else {
                $github[$entry['group']][$name] = $entry['description'];
            }
        }

        return ['github' => $github, 'pageant' => $pageant, 'worktree' => $worktree];
    }
}
