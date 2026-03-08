<?php

namespace App\Ai\Agents;

use App\Ai\ToolRegistry;
use App\Models\User;
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
            'You help users create and configure agents, work items, issues, pull requests, and other GitHub operations.',
            $this->repoFullName
                ? "You are operating on the GitHub repository: {$this->repoFullName}."
                : 'No repository is currently selected. You can list repos and projects, but GitHub-specific tools are unavailable until a repo is selected.',
            $this->repoFullName
                ? 'Use the available tools to interact with the repository when the user asks you to perform actions.'
                : null,
            'Be concise and helpful in your responses.',
            $this->pageContext ? "Current page context: {$this->pageContext}" : null,
        ]));
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
