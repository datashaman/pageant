<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddWorkspaceReferenceTool;
use App\Mcp\Tools\AttachSkillToAgentTool;
use App\Mcp\Tools\CloseWorkspaceIssueTool;
use App\Mcp\Tools\CreateAgentTool;
use App\Mcp\Tools\CreateSkillTool;
use App\Mcp\Tools\CreateWorkspaceIssueTool;
use App\Mcp\Tools\CreateWorkspaceTool;
use App\Mcp\Tools\DeleteWorkspaceTool;
use App\Mcp\Tools\GetWorkspaceTool;
use App\Mcp\Tools\ImportRegistrySkillTool;
use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListSkillsTool;
use App\Mcp\Tools\ListWorkspaceReferencesTool;
use App\Mcp\Tools\ListWorkspacesTool;
use App\Mcp\Tools\RemoveWorkspaceIssueTool;
use App\Mcp\Tools\RemoveWorkspaceReferenceTool;
use App\Mcp\Tools\ReopenWorkspaceIssueTool;
use App\Mcp\Tools\SearchAgentsTool;
use App\Mcp\Tools\SearchRegistrySkillsTool;
use App\Mcp\Tools\SearchSkillsTool;
use App\Mcp\Tools\UpdateWorkspaceTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pageant Server')]
#[Version('0.0.1')]
#[Instructions('Manage Pageant workspaces, workspace references, workspace issues, and agents.')]
class PageantServer extends Server
{
    protected array $tools = [
        // Workspaces
        ListWorkspacesTool::class,
        GetWorkspaceTool::class,
        CreateWorkspaceTool::class,
        UpdateWorkspaceTool::class,
        DeleteWorkspaceTool::class,

        // Workspace References
        ListWorkspaceReferencesTool::class,
        AddWorkspaceReferenceTool::class,
        RemoveWorkspaceReferenceTool::class,

        // Workspace Issues
        CreateWorkspaceIssueTool::class,
        CloseWorkspaceIssueTool::class,
        ReopenWorkspaceIssueTool::class,
        RemoveWorkspaceIssueTool::class,

        // Agents
        ListAgentsTool::class,
        SearchAgentsTool::class,
        CreateAgentTool::class,

        // Skills
        ListSkillsTool::class,
        SearchSkillsTool::class,
        CreateSkillTool::class,
        AttachSkillToAgentTool::class,

        // Skill Registry
        SearchRegistrySkillsTool::class,
        ImportRegistrySkillTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
