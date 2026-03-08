<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BashTool;
use App\Mcp\Tools\EditFileTool;
use App\Mcp\Tools\GitCommitTool;
use App\Mcp\Tools\GitDiffTool;
use App\Mcp\Tools\GitLogTool;
use App\Mcp\Tools\GitPushTool;
use App\Mcp\Tools\GitStatusTool;
use App\Mcp\Tools\GlobTool;
use App\Mcp\Tools\GrepTool;
use App\Mcp\Tools\ListDirectoryTool;
use App\Mcp\Tools\ReadFileTool;
use App\Mcp\Tools\WriteFileTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Worktree Server')]
#[Version('0.0.1')]
#[Instructions('Local file operations, shell commands, and git operations within a git worktree.')]
class WorktreeServer extends Server
{
    protected array $tools = [
        // File Tools
        ReadFileTool::class,
        WriteFileTool::class,
        EditFileTool::class,
        GlobTool::class,
        GrepTool::class,
        ListDirectoryTool::class,

        // Command
        BashTool::class,

        // Git
        GitStatusTool::class,
        GitDiffTool::class,
        GitCommitTool::class,
        GitPushTool::class,
        GitLogTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
