<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateAgentTool;
use App\Mcp\Tools\CreateWorkItemTool;
use App\Mcp\Tools\DeleteWorkItemTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pageant Server')]
#[Version('0.0.1')]
#[Instructions('Manage Pageant work items and agents.')]
class PageantServer extends Server
{
    protected array $tools = [
        // Work Items
        CreateWorkItemTool::class,
        DeleteWorkItemTool::class,

        // Agents
        CreateAgentTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
