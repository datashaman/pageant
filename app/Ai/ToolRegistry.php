<?php

namespace App\Ai;

use App\Ai\Tools\AddLabelsToIssueTool;
use App\Ai\Tools\CloseIssueTool;
use App\Ai\Tools\CreateBranchTool;
use App\Ai\Tools\CreateCommentTool;
use App\Ai\Tools\CreateIssueTool;
use App\Ai\Tools\CreateLabelTool;
use App\Ai\Tools\CreateOrUpdateFileTool;
use App\Ai\Tools\CreatePullRequestReviewTool;
use App\Ai\Tools\CreatePullRequestTool;
use App\Ai\Tools\CreateWorkItemTool;
use App\Ai\Tools\DeleteFileTool;
use App\Ai\Tools\DeleteLabelTool;
use App\Ai\Tools\DeleteWorkItemTool;
use App\Ai\Tools\GetCommitStatusTool;
use App\Ai\Tools\GetFileContentsTool;
use App\Ai\Tools\GetIssueTool;
use App\Ai\Tools\GetPullRequestTool;
use App\Ai\Tools\GetRepositoryTreeTool;
use App\Ai\Tools\ListBranchesTool;
use App\Ai\Tools\ListCheckRunsTool;
use App\Ai\Tools\ListCommentsTool;
use App\Ai\Tools\ListIssueLabelsTool;
use App\Ai\Tools\ListIssuesTool;
use App\Ai\Tools\ListLabelsTool;
use App\Ai\Tools\ListPullRequestFilesTool;
use App\Ai\Tools\ListPullRequestsTool;
use App\Ai\Tools\MergePullRequestTool;
use App\Ai\Tools\RemoveLabelFromIssueTool;
use App\Ai\Tools\RequestReviewersTool;
use App\Ai\Tools\SearchCodeTool;
use App\Ai\Tools\SearchIssuesTool;
use App\Ai\Tools\UpdateIssueTool;
use App\Ai\Tools\UpdatePullRequestTool;
use App\Models\GithubInstallation;
use App\Models\Repo;
use App\Services\GitHubService;
use Laravel\Ai\Contracts\Tool;

class ToolRegistry
{
    /** @var array<string, class-string<Tool>> */
    private const TOOL_MAP = [
        'add_labels_to_issue' => AddLabelsToIssueTool::class,
        'close_issue' => CloseIssueTool::class,
        'create_branch' => CreateBranchTool::class,
        'create_comment' => CreateCommentTool::class,
        'create_issue' => CreateIssueTool::class,
        'create_label' => CreateLabelTool::class,
        'create_work_item' => CreateWorkItemTool::class,
        'create_or_update_file' => CreateOrUpdateFileTool::class,
        'create_pull_request' => CreatePullRequestTool::class,
        'create_pull_request_review' => CreatePullRequestReviewTool::class,
        'delete_file' => DeleteFileTool::class,
        'delete_label' => DeleteLabelTool::class,
        'delete_work_item' => DeleteWorkItemTool::class,
        'get_commit_status' => GetCommitStatusTool::class,
        'get_file_contents' => GetFileContentsTool::class,
        'get_issue' => GetIssueTool::class,
        'get_pull_request' => GetPullRequestTool::class,
        'get_repository_tree' => GetRepositoryTreeTool::class,
        'list_branches' => ListBranchesTool::class,
        'list_check_runs' => ListCheckRunsTool::class,
        'list_comments' => ListCommentsTool::class,
        'list_issue_labels' => ListIssueLabelsTool::class,
        'list_issues' => ListIssuesTool::class,
        'list_labels' => ListLabelsTool::class,
        'list_pull_request_files' => ListPullRequestFilesTool::class,
        'list_pull_requests' => ListPullRequestsTool::class,
        'merge_pull_request' => MergePullRequestTool::class,
        'remove_label_from_issue' => RemoveLabelFromIssueTool::class,
        'request_reviewers' => RequestReviewersTool::class,
        'search_code' => SearchCodeTool::class,
        'search_issues' => SearchIssuesTool::class,
        'update_issue' => UpdateIssueTool::class,
        'update_pull_request' => UpdatePullRequestTool::class,
    ];

    /**
     * @param  array<int, string>  $toolNames
     * @return Tool[]
     */
    public static function resolve(array $toolNames, string $repoFullName): array
    {
        if (empty($toolNames)) {
            return [];
        }

        $github = app(GitHubService::class);
        $repo = Repo::where('source', 'github')
            ->where('source_reference', $repoFullName)
            ->firstOrFail();
        $installation = GithubInstallation::where('organization_id', $repo->organization_id)->firstOrFail();

        $tools = [];

        foreach ($toolNames as $name) {
            if (isset(self::TOOL_MAP[$name])) {
                $tools[] = new (self::TOOL_MAP[$name])($github, $installation, $repoFullName);
            }
        }

        return $tools;
    }

    /**
     * @return array<string, class-string<Tool>>
     */
    public static function available(): array
    {
        return self::TOOL_MAP;
    }
}
