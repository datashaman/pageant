<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddLabelsToIssueTool;
use App\Mcp\Tools\CloseIssueTool;
use App\Mcp\Tools\CreateAgentTool;
use App\Mcp\Tools\CreateBranchTool;
use App\Mcp\Tools\CreateCommentTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\CreateOrUpdateFileTool;
use App\Mcp\Tools\CreatePullRequestReviewTool;
use App\Mcp\Tools\CreatePullRequestTool;
use App\Mcp\Tools\CreateWorkItemTool;
use App\Mcp\Tools\DeleteFileTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\DeleteWorkItemTool;
use App\Mcp\Tools\GetCommitStatusTool;
use App\Mcp\Tools\GetFileContentsTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetPullRequestDiffTool;
use App\Mcp\Tools\GetPullRequestTool;
use App\Mcp\Tools\GetRepositoryTreeTool;
use App\Mcp\Tools\ListBranchesTool;
use App\Mcp\Tools\ListCheckRunsTool;
use App\Mcp\Tools\ListCommentsTool;
use App\Mcp\Tools\ListIssueLabelsTool;
use App\Mcp\Tools\ListIssuesTool;
use App\Mcp\Tools\ListLabelsTool;
use App\Mcp\Tools\ListPullRequestFilesTool;
use App\Mcp\Tools\ListPullRequestsTool;
use App\Mcp\Tools\MergePullRequestTool;
use App\Mcp\Tools\RemoveLabelFromIssueTool;
use App\Mcp\Tools\RequestReviewersTool;
use App\Mcp\Tools\SearchCodeTool;
use App\Mcp\Tools\SearchIssuesTool;
use App\Mcp\Tools\UpdateIssueTool;
use App\Mcp\Tools\UpdatePullRequestTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('GitHub Server')]
#[Version('0.0.1')]
#[Instructions('Manage GitHub repositories: read/write files, issues, pull requests, branches, labels, comments, reviews, CI status, and search on tracked repositories.')]
class GitHubServer extends Server
{
    protected array $tools = [
        // Issues
        GetIssueTool::class,
        ListIssuesTool::class,
        CreateIssueTool::class,
        UpdateIssueTool::class,
        CloseIssueTool::class,

        // Comments
        ListCommentsTool::class,
        CreateCommentTool::class,

        // Pull Requests
        GetPullRequestTool::class,
        ListPullRequestsTool::class,
        CreatePullRequestTool::class,
        UpdatePullRequestTool::class,
        MergePullRequestTool::class,
        ListPullRequestFilesTool::class,
        GetPullRequestDiffTool::class,
        RequestReviewersTool::class,
        CreatePullRequestReviewTool::class,

        // CI / Status
        GetCommitStatusTool::class,
        ListCheckRunsTool::class,

        // Branches
        ListBranchesTool::class,
        CreateBranchTool::class,

        // Files
        GetFileContentsTool::class,
        GetRepositoryTreeTool::class,
        CreateOrUpdateFileTool::class,
        DeleteFileTool::class,

        // Search
        SearchCodeTool::class,
        SearchIssuesTool::class,

        // Work Items
        CreateWorkItemTool::class,
        DeleteWorkItemTool::class,

        // Agents
        CreateAgentTool::class,

        // Labels
        ListLabelsTool::class,
        ListIssueLabelsTool::class,
        AddLabelsToIssueTool::class,
        RemoveLabelFromIssueTool::class,
        CreateLabelTool::class,
        DeleteLabelTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
