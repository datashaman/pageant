<?php

namespace App\Ai;

use App\Ai\Tools\AddLabelsToIssueTool;
use App\Ai\Tools\AddPlanStepTool;
use App\Ai\Tools\ApprovePlanTool;
use App\Ai\Tools\AttachRepoToProjectTool;
use App\Ai\Tools\AttachSkillToAgentTool;
use App\Ai\Tools\BashTool;
use App\Ai\Tools\CancelPlanTool;
use App\Ai\Tools\CloseIssueTool;
use App\Ai\Tools\CloseWorkItemTool;
use App\Ai\Tools\CreateAgentTool;
use App\Ai\Tools\CreateBranchTool;
use App\Ai\Tools\CreateCommentTool;
use App\Ai\Tools\CreateIssueTool;
use App\Ai\Tools\CreateLabelTool;
use App\Ai\Tools\CreatePlanTool;
use App\Ai\Tools\CreateProjectTool;
use App\Ai\Tools\CreatePullRequestReviewTool;
use App\Ai\Tools\CreatePullRequestTool;
use App\Ai\Tools\CreateSkillTool;
use App\Ai\Tools\CreateWorkItemTool;
use App\Ai\Tools\DeleteLabelTool;
use App\Ai\Tools\DeleteProjectTool;
use App\Ai\Tools\DeleteRepoTool;
use App\Ai\Tools\DetachRepoFromProjectTool;
use App\Ai\Tools\EditFileTool;
use App\Ai\Tools\GetCommitStatusTool;
use App\Ai\Tools\GetIssueTool;
use App\Ai\Tools\GetPlanTool;
use App\Ai\Tools\GetProjectTool;
use App\Ai\Tools\GetPullRequestDiffTool;
use App\Ai\Tools\GetPullRequestTool;
use App\Ai\Tools\GetRepoTool;
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
use App\Ai\Tools\ListProjectsTool;
use App\Ai\Tools\ListPullRequestFilesTool;
use App\Ai\Tools\ListPullRequestsTool;
use App\Ai\Tools\ListReposTool;
use App\Ai\Tools\ListSkillsTool;
use App\Ai\Tools\MergePullRequestTool;
use App\Ai\Tools\PausePlanTool;
use App\Ai\Tools\ReadFileTool;
use App\Ai\Tools\RemoveLabelFromIssueTool;
use App\Ai\Tools\ReopenWorkItemTool;
use App\Ai\Tools\RequestReviewersTool;
use App\Ai\Tools\ResumePlanTool;
use App\Ai\Tools\SearchAgentsTool;
use App\Ai\Tools\SearchIssuesTool;
use App\Ai\Tools\SearchRegistrySkillsTool;
use App\Ai\Tools\SearchSkillsTool;
use App\Ai\Tools\UpdateIssueTool;
use App\Ai\Tools\UpdateProjectTool;
use App\Ai\Tools\UpdatePullRequestTool;
use App\Ai\Tools\UpdateRepoTool;
use App\Ai\Tools\WriteFileTool;
use App\Contracts\ExecutionDriver;
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

        // Work Items
        'create_work_item' => ['class' => CreateWorkItemTool::class, 'description' => 'Create a work item from an issue', 'group' => 'Work Items', 'category' => 'pageant', 'flexible' => true],
        'close_work_item' => ['class' => CloseWorkItemTool::class, 'description' => 'Close a work item', 'group' => 'Work Items', 'category' => 'pageant', 'flexible' => true],
        'reopen_work_item' => ['class' => ReopenWorkItemTool::class, 'description' => 'Reopen a closed work item', 'group' => 'Work Items', 'category' => 'pageant', 'flexible' => true],

        // Agents
        'create_agent' => ['class' => CreateAgentTool::class, 'description' => 'Create a new agent', 'group' => 'Agents', 'local' => true],
        'list_agents' => ['class' => ListAgentsTool::class, 'description' => 'List agents in the organization', 'group' => 'Agents', 'local' => true],
        'search_agents' => ['class' => SearchAgentsTool::class, 'description' => 'Search for agents matching work item requirements', 'group' => 'Agents', 'local' => true],

        // Skills
        'list_skills' => ['class' => ListSkillsTool::class, 'description' => 'List skills in the organization', 'group' => 'Skills', 'local' => true],
        'search_skills' => ['class' => SearchSkillsTool::class, 'description' => 'Search skills by capability', 'group' => 'Skills', 'local' => true],
        'create_skill' => ['class' => CreateSkillTool::class, 'description' => 'Create a new skill', 'group' => 'Skills', 'local' => true],
        'attach_skill_to_agent' => ['class' => AttachSkillToAgentTool::class, 'description' => 'Attach a skill to an agent', 'group' => 'Skills', 'local' => true],
        'search_registry_skills' => ['class' => SearchRegistrySkillsTool::class, 'description' => 'Search public registries for skills to import', 'group' => 'Skills', 'local' => true],
        'import_registry_skill' => ['class' => ImportRegistrySkillTool::class, 'description' => 'Import a skill from a public registry', 'group' => 'Skills', 'local' => true],

        // Plans
        'create_plan' => ['class' => CreatePlanTool::class, 'description' => 'Create an execution plan for a work item', 'group' => 'Plans', 'local' => true],
        'get_plan' => ['class' => GetPlanTool::class, 'description' => 'Get a plan with its steps', 'group' => 'Plans', 'local' => true],
        'list_plans' => ['class' => ListPlansTool::class, 'description' => 'List plans for work items', 'group' => 'Plans', 'local' => true],
        'approve_plan' => ['class' => ApprovePlanTool::class, 'description' => 'Approve a pending plan for execution', 'group' => 'Plans', 'local' => true],
        'cancel_plan' => ['class' => CancelPlanTool::class, 'description' => 'Cancel a pending or running plan', 'group' => 'Plans', 'local' => true],
        'add_plan_step' => ['class' => AddPlanStepTool::class, 'description' => 'Add a step to an existing plan', 'group' => 'Plans', 'local' => true],
        'pause_plan' => ['class' => PausePlanTool::class, 'description' => 'Pause a running plan', 'group' => 'Plans', 'local' => true],
        'resume_plan' => ['class' => ResumePlanTool::class, 'description' => 'Resume a paused plan', 'group' => 'Plans', 'local' => true],

        // Repos (organization-scoped, no GitHub API needed)
        'list_repos' => ['class' => ListReposTool::class, 'description' => 'List repos in the current organization', 'group' => 'Repos', 'local' => true],
        'get_repo' => ['class' => GetRepoTool::class, 'description' => 'Get a repo by ID', 'group' => 'Repos', 'local' => true],
        'update_repo' => ['class' => UpdateRepoTool::class, 'description' => 'Update a repo name', 'group' => 'Repos', 'local' => true],
        'delete_repo' => ['class' => DeleteRepoTool::class, 'description' => 'Delete a repo', 'group' => 'Repos', 'local' => true],

        // Projects
        'list_projects' => ['class' => ListProjectsTool::class, 'description' => 'List projects in the current organization', 'group' => 'Projects', 'local' => true],
        'get_project' => ['class' => GetProjectTool::class, 'description' => 'Get a project by ID', 'group' => 'Projects', 'local' => true],
        'create_project' => ['class' => CreateProjectTool::class, 'description' => 'Create a project', 'group' => 'Projects', 'local' => true],
        'update_project' => ['class' => UpdateProjectTool::class, 'description' => 'Update a project', 'group' => 'Projects', 'local' => true],
        'delete_project' => ['class' => DeleteProjectTool::class, 'description' => 'Delete a project', 'group' => 'Projects', 'local' => true],
        'attach_repo_to_project' => ['class' => AttachRepoToProjectTool::class, 'description' => 'Attach a repo to a project', 'group' => 'Projects', 'local' => true],
        'detach_repo_from_project' => ['class' => DetachRepoFromProjectTool::class, 'description' => 'Detach a repo from a project', 'group' => 'Projects', 'local' => true],

        // Worktree - File Tools
        'read_file' => ['class' => ReadFileTool::class, 'description' => 'Read file contents from the worktree', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],
        'write_file' => ['class' => WriteFileTool::class, 'description' => 'Create or overwrite a file in the worktree', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],
        'edit_file' => ['class' => EditFileTool::class, 'description' => 'Edit a file using exact string replacement', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],
        'glob' => ['class' => GlobTool::class, 'description' => 'Find files by glob pattern', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],
        'grep' => ['class' => GrepTool::class, 'description' => 'Search file contents with regex', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],
        'list_directory' => ['class' => ListDirectoryTool::class, 'description' => 'List files and directories', 'group' => 'Worktree Files', 'worktree' => true, 'category' => 'worktree'],

        // Worktree - Command
        'bash' => ['class' => BashTool::class, 'description' => 'Execute a shell command in the worktree', 'group' => 'Worktree Commands', 'worktree' => true, 'category' => 'worktree'],

        // Worktree - Git
        'git_status' => ['class' => GitStatusTool::class, 'description' => 'Show working tree status', 'group' => 'Worktree Git', 'worktree' => true, 'category' => 'worktree'],
        'git_diff' => ['class' => GitDiffTool::class, 'description' => 'Show changes in the worktree', 'group' => 'Worktree Git', 'worktree' => true, 'category' => 'worktree'],
        'git_commit' => ['class' => GitCommitTool::class, 'description' => 'Stage and commit changes', 'group' => 'Worktree Git', 'worktree' => true, 'category' => 'worktree'],
        'git_push' => ['class' => GitPushTool::class, 'description' => 'Push commits to remote', 'group' => 'Worktree Git', 'worktree' => true, 'category' => 'worktree'],
        'git_log' => ['class' => GitLogTool::class, 'description' => 'View commit history', 'group' => 'Worktree Git', 'worktree' => true, 'category' => 'worktree'],
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
