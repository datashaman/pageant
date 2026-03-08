<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttachRepoToProjectTool;
use App\Mcp\Tools\AttachSkillToAgentTool;
use App\Mcp\Tools\CloseWorkItemTool;
use App\Mcp\Tools\CreateAgentTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateSkillTool;
use App\Mcp\Tools\CreateWorkItemTool;
use App\Mcp\Tools\DeleteProjectTool;
use App\Mcp\Tools\DeleteRepoTool;
use App\Mcp\Tools\DetachRepoFromProjectTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetRepoTool;
use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListReposTool;
use App\Mcp\Tools\ListSkillsTool;
use App\Mcp\Tools\ReopenWorkItemTool;
use App\Mcp\Tools\SearchAgentsTool;
use App\Mcp\Tools\SearchSkillsTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateRepoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pageant Server')]
#[Version('0.0.1')]
#[Instructions('Manage Pageant repos, projects, work items, and agents.')]
class PageantServer extends Server
{
    protected array $tools = [
        // Repos
        ListReposTool::class,
        GetRepoTool::class,
        UpdateRepoTool::class,
        DeleteRepoTool::class,

        // Projects
        ListProjectsTool::class,
        GetProjectTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        DeleteProjectTool::class,
        AttachRepoToProjectTool::class,
        DetachRepoFromProjectTool::class,

        // Work Items
        CreateWorkItemTool::class,
        CloseWorkItemTool::class,
        ReopenWorkItemTool::class,

        // Agents
        ListAgentsTool::class,
        SearchAgentsTool::class,
        CreateAgentTool::class,

        // Skills
        ListSkillsTool::class,
        SearchSkillsTool::class,
        CreateSkillTool::class,
        AttachSkillToAgentTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
