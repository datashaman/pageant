# Pageant

Pageant is a GitHub App integration platform that connects repositories with AI agents via the Model Context Protocol (MCP). It provides 62 tools across three MCP servers, real-time webhook event handling, work item tracking with automated status reconciliation, and a web interface for managing agents, repositories, skills, and projects. The platform includes advanced agent capabilities: multi-model routing, conversation compression, failure-aware retry policies, checkpoint-based plan recovery, structural codebase indexing, dynamic prompt assembly, and cross-session agent memory.

## Features

- **MCP Servers**:
  - **GitHub Server** (`/mcp/github`) — 27 tools for repository operations: issues, PRs, branches, labels, CI status, and search
  - **Pageant Server** (`/mcp/pageant`) — 23 tools for repos, projects, work items, agents, skills, and skill registry
  - **Worktree Server** (`/mcp/worktree`) — 12 tools for file operations, shell commands, and git within worktrees
- **AI Agent Management** — Create agents with configurable tool access, event subscriptions, providers (Anthropic/OpenAI/Gemini), permission modes, and skill attachments
- **Multi-Model Routing** — Agents use a secondary model (cheapest/smartest/specific) for cost-optimized validation, webhook filtering, and plan step verification
- **BYOK (Bring Your Own Key)** — Users can supply their own API keys for Anthropic, OpenAI, and Gemini providers
- **Agent Memory** — Cross-session learning from plan outcomes; memories scored by recency and importance, pruned automatically
- **Conversation Compression** — Long-running agent conversations are compressed when approaching context limits, preserving user messages and summarizing older content
- **Failure Classification & Retry** — Exceptions are categorized (rate limit, API error, timeout, etc.) with per-category retry policies using exponential backoff
- **Checkpoint Plan Recovery** — Plans can resume from partial steps after failures instead of restarting from scratch
- **Structural Codebase Indexing** — Tree-sitter-style parsing of PHP, JS, TS, and Python files to build token-counted structural maps cached per commit
- **Dynamic Prompt Assembly** — Layered system prompts built from organization policies, repo instructions, agent config, skill contexts, and execution state
- **Webhook Relevance Filtering** — Pre-screens webhook events for relevance using secondary models before dispatching to agents
- **Graceful Degradation** — Plans emit partial progress events and broadcast status when hitting turn/step limits
- **Skill Registry** — Import skills from public registries (official MCP Registry, Smithery) or create custom skills
- **GitHub Webhooks** — Real-time event handling for issues, pull requests, comments, reviews, and pushes
- **Work Items** — Bridge GitHub issues to internal project tracking with status reconciliation, plans, and conversation history
- **Chat Assistant** — Context-aware AI chat with repo-level instruction loading and page context awareness
- **Multi-Organization** — Support for multiple GitHub organizations per user with cross-tenant isolation
- **Setup Scripts** — Per-repo setup scripts executed automatically when provisioning worktrees
- **OAuth 2.1** — Secure MCP server access via Laravel Passport
- **Login Restrictions** — Optional email allowlist to restrict access

## Tech Stack

- PHP 8.4+ / Laravel 12
- Livewire 4 / Flux UI 2
- Tailwind CSS 4
- Laravel Passport (OAuth 2.1)
- Laravel Fortify (authentication)
- Pest 4 (testing)

## Documentation

- [Installation Guide](INSTALL.md) — Setup, configuration, and deployment
- [MCP Tools Reference](#mcp-tools) — All available MCP tools
- [Webhook Events](#webhook-events) — Supported GitHub events
- [Architecture](#architecture) — System design and data flow

---

## MCP Tools

### GitHub Server (`/mcp/github`)

All GitHub tools require a `repo` parameter (format: `owner/repo`). API calls use GitHub App installation tokens (not user OAuth tokens).

#### Issues

| Tool | Description |
|------|-------------|
| `get-issue` | Get a single issue by number |
| `list-issues` | List open issues (excludes PRs) |
| `create-issue` | Create a new issue |
| `update-issue` | Update an issue |
| `close-issue` | Close an issue |
| `search-issues` | Search issues and PRs |

#### Pull Requests

| Tool | Description |
|------|-------------|
| `get-pull-request` | Get a PR by number |
| `list-pull-requests` | List PRs by state |
| `create-pull-request` | Create a PR |
| `update-pull-request` | Update a PR |
| `merge-pull-request` | Merge a PR |
| `list-pull-request-files` | List changed files |
| `get-pull-request-diff` | Get unified diff |
| `create-pull-request-review` | Submit a review |
| `request-reviewers` | Request reviewers |

#### Comments

| Tool | Description |
|------|-------------|
| `list-comments` | List comments on an issue or PR |
| `create-comment` | Add a comment |

#### Branches

| Tool | Description |
|------|-------------|
| `list-branches` | List branches |
| `create-branch` | Create a branch |

#### Labels

| Tool | Description |
|------|-------------|
| `list-labels` | List all labels |
| `list-issue-labels` | List labels on an issue |
| `add-labels-to-issue` | Add labels to an issue |
| `remove-label-from-issue` | Remove a label |
| `create-label` | Create a label |
| `delete-label` | Delete a label |

#### CI/CD Status

| Tool | Description |
|------|-------------|
| `get-commit-status` | Get commit status for a ref |
| `list-check-runs` | List check runs for a ref |

### Pageant Server (`/mcp/pageant`)

| Group | Tool | Description |
|-------|------|-------------|
| Repos | `list-repos` | List repos in the organization |
| Repos | `get-repo` | Get a repo by ID |
| Repos | `update-repo` | Update a repo |
| Repos | `delete-repo` | Delete a repo |
| Projects | `list-projects` | List projects |
| Projects | `get-project` | Get a project by ID |
| Projects | `create-project` | Create a project |
| Projects | `update-project` | Update a project |
| Projects | `delete-project` | Delete a project |
| Projects | `attach-repo-to-project` | Attach a repo to a project |
| Projects | `detach-repo-from-project` | Detach a repo from a project |
| Work Items | `create-work-item` | Create a work item from a GitHub issue |
| Work Items | `close-work-item` | Close a work item |
| Work Items | `reopen-work-item` | Reopen a closed work item |
| Agents | `list-agents` | List agents |
| Agents | `search-agents` | Search agents by capability |
| Agents | `create-agent` | Create an agent |
| Skills | `list-skills` | List skills |
| Skills | `search-skills` | Search skills (with optional public registry search) |
| Skills | `create-skill` | Create a skill |
| Skills | `attach-skill-to-agent` | Attach a skill to an agent |
| Registry | `search-registry-skills` | Search public registries (MCP Registry, Smithery) |
| Registry | `import-registry-skill` | Import a skill from a public registry |

### Worktree Server (`/mcp/worktree`)

| Tool | Description |
|------|-------------|
| `read-file` | Read file contents |
| `write-file` | Create or overwrite a file |
| `edit-file` | Edit via exact string replacement |
| `glob` | Find files by glob pattern |
| `grep` | Search file contents with regex |
| `list-directory` | List files and directories |
| `bash` | Execute a shell command |
| `git-status` | Show working tree status |
| `git-diff` | Show changes |
| `git-commit` | Stage and commit |
| `git-push` | Push to remote |
| `git-log` | View commit history |

---

## Webhook Events

Pageant handles the following GitHub webhook events and dispatches them to subscribed agents:

| Event | Laravel Event | Description |
|-------|--------------|-------------|
| `issues` | `GitHubIssueReceived` | Issue opened, closed, edited, labeled, etc. |
| `pull_request` | `GitHubPullRequestReceived` | PR opened, closed, synchronized, etc. |
| `issue_comment` | `GitHubCommentReceived` | Comment created, edited, or deleted |
| `pull_request_review` | `GitHubPullRequestReviewReceived` | Review submitted on a PR |
| `push` | `GitHubPushReceived` | Push to a branch |
| `installation` | *(handled directly)* | App installed, uninstalled, suspended, unsuspended |

Internal events: `WorkItemCreated`, `WorkItemDeleted`, `PlanStepCompleted`, `PlanStepFailed`, `PlanStepPartial`, `PlanCompleted`, `PlanFailed`, `PlanLimitReached`.

When an event is received:

1. Webhook signature is verified (HMAC-SHA256)
2. A Laravel event is dispatched
3. Listeners find agents subscribed to that event for the affected repo
4. Agent jobs are dispatched with event context

---

## Architecture

### Models

```
User
├── organizations [BelongsToMany] → Organization
├── currentOrganization [BelongsTo] → Organization
└── apiKeys [HasMany] → UserApiKey

Organization
├── users [BelongsToMany] → User
├── repos [HasMany] → Repo
├── agents [HasMany] → Agent
├── skills [HasMany] → Skill
├── projects [HasMany] → Project
├── workItems [HasMany] → WorkItem
├── agentMemories [HasMany] → AgentMemory
├── githubInstallation [HasOne] → GithubInstallation
└── policies — Organization-level constraints for prompt assembly

Repo
├── organization [BelongsTo] → Organization
├── agents [BelongsToMany] → Agent
├── skills [BelongsToMany] → Skill
├── projects [BelongsToMany] → Project
├── indices [HasMany] → RepoIndex
├── latestIndex — Most recent structural index
├── setup_script — Bash script run during worktree provisioning
└── inferProjectId() — Returns project ID if repo belongs to exactly one project

Agent
├── organization [BelongsTo] → Organization
├── repos [BelongsToMany] → Repo
├── skills [BelongsToMany] → Skill
├── tools — JSON array of tool identifiers
├── events — JSON array of event subscriptions
├── provider — LLM provider (anthropic/openai/gemini)
├── model — Model name or "inherit" (displays as "Default")
├── secondary_model — Cost-optimized model for validation/filtering (cheapest/smartest/specific)
├── permission_mode — Command execution policy
└── max_turns — Max conversation turns

Skill
├── organization [BelongsTo] → Organization
├── agent [BelongsTo] → Agent (optional)
├── agents [BelongsToMany] → Agent
├── repos [BelongsToMany] → Repo
├── source — Registry source (mcp-registry, smithery, github, etc.)
├── source_reference — Qualified name in registry
└── allowed_tools — JSON array of permitted tools

Project
├── organization [BelongsTo] → Organization
├── repos [BelongsToMany] → Repo
└── workItems [HasMany] → WorkItem

WorkItem
├── organization [BelongsTo] → Organization
├── project [BelongsTo] → Project
├── plans [HasMany] → Plan
└── status — open/closed, reconciled with GitHub issue state

PlanStep
├── plan [BelongsTo] → Plan
├── failure_category — FailureCategory enum (RateLimit, GithubApi, ToolError, etc.)
├── retry_attempts — Number of retries attempted
├── validation_status — pass/fail/uncertain from secondary model
├── validation_reason — Explanation of validation result
├── progress_summary — Human-readable progress description
└── turns_used — Conversation turns consumed

UserApiKey
├── user [BelongsTo] → User
├── provider — API provider (anthropic, openai, gemini)
├── key — Encrypted API key
└── validated_at — Last successful validation timestamp

AgentMemory
├── organization [BelongsTo] → Organization
├── repo [BelongsTo] → Repo
├── agent [BelongsTo] → Agent
├── content — Learned insight from plan execution
├── importance — Float score for retrieval ranking
└── metadata — Source plan/work item context

RepoIndex
├── repo [BelongsTo] → Repo
├── commit_hash — Git commit the index was built from
├── structure — Parsed structural map (classes, methods, functions)
└── token_count — Token budget consumed by the index
```

### Authentication Flow

1. User visits `/auth/github` → redirected to GitHub OAuth
2. On callback, email is validated against optional `ALLOWED_EMAILS` allowlist
3. User is created/updated, GitHub App installations are synced to organizations
4. All GitHub API calls use installation tokens (not user OAuth tokens)
5. MCP clients authenticate via OAuth 2.1 (Passport) at `/mcp` endpoints

### Work Item Status Reconciliation

Work item statuses are kept in sync with their corresponding GitHub issues:

- **Hourly scheduler**: `ReconcileWorkItemStatuses` job runs hourly
- **Page load**: Synchronous reconciliation when viewing work items index
- **Manual sync**: Button on work items page triggers immediate sync
- **Webhook-driven**: `SyncWorkItemStatus` listener updates status on GitHub issue events

### Chat Assistant

The built-in chat assistant provides context-aware AI help:

- Resolves repo context from the current page (`data-chat-context` attributes)
- Loads repo-level instructions from `CLAUDE.md`, `.github/copilot-instructions.md`, `AGENTS.md` (cached 1 hour, max 4000 chars)
- Persists messages eagerly before streaming (survives stream errors)
- Validates conversation ownership per user

### Agent Dispatch Flow

1. GitHub sends a webhook to `/webhooks/github`
2. The webhook controller verifies the signature and dispatches a Laravel event
3. Event listeners find agents subscribed to that event type for the affected repo
4. `WebhookRelevanceFilter` pre-screens events using the agent's secondary model — irrelevant events are skipped
5. Agent jobs are dispatched with event context and dynamically assembled system prompts
6. `PromptAssembler` builds layered prompts: org policies → repo instructions → agent config → skill contexts → codebase index → execution state
7. Agents execute plans with steps, using worktrees for code operations
8. `PlanStepValidator` verifies step outputs using the secondary model
9. On failure, `FailureClassifier` categorizes the error and `RetryPolicy` determines retry strategy with exponential backoff
10. Plans can resume from checkpoints (partial steps) instead of restarting
11. `ConversationCompressor` compresses context when approaching token limits
12. On completion/failure, `StoreAgentMemory` records learnings for future runs

### Agent Memory

- `AgentMemoryService` stores insights from completed and failed plans
- Memories are scored by composite of recency (40%) and importance (60%)
- Retrieved memories are injected into prompts within a 500-token budget
- `PruneAgentMemories` command (`agent-memories:prune`) removes memories older than the configured retention period (default 90 days)
- Scheduled daily via `routes/console.php`

### Multi-Model Routing

Agents support a `secondary_model` setting for cost-optimized operations:

- **Webhook relevance filtering** — Checks if an event is worth processing before invoking the primary model
- **Plan step validation** — Verifies step outputs against expected outcomes
- **Conversation compression** — Summarizes older messages when context fills up

The secondary model can be set to `cheapest` (auto-select cheapest available), `smartest` (auto-select most capable), or a specific model name. User-provided API keys (BYOK) are injected when available.

### Structural Codebase Indexing

- `RepoIndexer` parses PHP, JavaScript, TypeScript, and Python files to extract structural maps (classes, methods, functions, interfaces, traits, enums)
- Indexes are cached per commit hash in `RepoIndex` — only rebuilt when the repo changes
- Token-counted summaries are included in agent prompts for codebase awareness

### Worktree Management

- `WorktreeManager` creates bare clones and feature branches for work items
- Per-repo `setup_script` runs automatically during provisioning (300s timeout)
- Worktrees are cleaned up after execution via `git worktree remove --force`

## License

Proprietary.
