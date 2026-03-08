<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Models\User;
use App\Services\RepoInstructionsService;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent as AgentContract;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class PageantAssistant implements AgentContract, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        protected User $user,
        protected ?string $repoFullName = null,
        protected string $pageContext = '',
    ) {}

    public function instructions(): string
    {
        return implode("\n\n", array_filter([
            'You are a helpful Pageant assistant. Pageant is a platform for managing GitHub repositories, agents, work items, and projects.',
            'You help users manage agents, work items, issues, pull requests, and other GitHub operations.',
            $this->repoFullName
                ? "You are operating on the GitHub repository: {$this->repoFullName}. Use the available tools to interact with the repository when the user asks you to perform actions."
                : 'No repository is currently selected. You have tools to list repos and manage projects. If the user wants to perform GitHub operations (issues, PRs, etc.), use the list_repos tool to show available repos and ask which one to use.',
            'Be concise. Do not use emojis. Give short, direct answers.',
            'Act immediately when the user\'s intent is clear — do not ask for confirmation on obvious next steps. For example, if the user says "create an issue for X", create it directly without asking "are you sure?".',
            'Exception: For any destructive or irreversible action (such as deleting repositories, projects, work items, or labels, performing force pushes, or merging branches/PRs), always require an explicit user instruction or confirmation before invoking the corresponding tools, even if it seems like an obvious next step.',
            'Batch related operations together. For example, when creating issues, also create corresponding work items without asking. When setting up a project, perform all logical setup steps in sequence, as long as they are non-destructive.',
            'When context narrows to a single option (one repo, one agent, one project, etc.), use it without asking the user to confirm the selection, except when choosing would immediately lead to a destructive or irreversible action.',
            'Only ask clarifying questions when there is genuine ambiguity that cannot be resolved from context, or when the user appears to be requesting a destructive or irreversible action and you do not yet have explicit confirmation.',
            $this->pageContext ? "Current page context: {$this->pageContext}" : null,
            $this->repoFullName ? $this->loadRepoInstructions() : null,
        ]));
    }

    protected function loadRepoInstructions(): string
    {
        return app(RepoInstructionsService::class)->loadForRepo($this->repoFullName);
    }

    public function tools(): iterable
    {
        return ToolRegistry::resolve(
            array_keys(ToolRegistry::availableForContext($this->repoFullName)),
            $this->repoFullName,
            $this->user,
        );
    }
}
