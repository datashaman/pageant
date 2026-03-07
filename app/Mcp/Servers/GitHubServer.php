<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddLabelsToIssueTool;
use App\Mcp\Tools\CloseIssueTool;
use App\Mcp\Tools\CreateCommentTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\CreatePullRequestTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\ListIssueLabelsTool;
use App\Mcp\Tools\ListLabelsTool;
use App\Mcp\Tools\RemoveLabelFromIssueTool;
use App\Mcp\Tools\UpdateIssueTool;
use App\Mcp\Tools\UpdatePullRequestTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('GitHub Server')]
#[Version('0.0.1')]
#[Instructions('Manage GitHub issues, pull requests, and labels on tracked repositories.')]
class GitHubServer extends Server
{
    protected array $tools = [
        CreateIssueTool::class,
        UpdateIssueTool::class,
        CloseIssueTool::class,
        CreateCommentTool::class,
        CreatePullRequestTool::class,
        UpdatePullRequestTool::class,
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
