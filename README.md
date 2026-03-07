# Pageant

Pageant is a GitHub App integration platform that connects repositories with AI agents via the Model Context Protocol (MCP). It provides a comprehensive MCP server with 35+ GitHub tools, real-time webhook event handling, and a web interface for managing agents, repositories, skills, and projects.

## Features

- **MCP Servers**:
  - **GitHub Server** (`/mcp/github`) — 32 tools for repository operations: issues, PRs, branches, files, labels, CI status, and search
  - **Pageant Server** (`/mcp/pageant`) — 3 tools for work item and agent management
- **AI Agent Management** — Create agents with configurable tool access, event subscriptions, providers (Anthropic/OpenAI), and permission modes
- **GitHub Webhooks** — Real-time event handling for issues, pull requests, comments, reviews, and pushes
- **Work Items** — Bridge GitHub issues to internal project tracking with conversation history
- **Multi-Organization** — Support for multiple GitHub organizations per user
- **OAuth 2.1** — Secure MCP server access via Laravel Passport

## Tech Stack

- PHP 8.4+ / Laravel 12
- Livewire 4 / Flux UI
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

All GitHub tools require a `repo` parameter (format: `owner/repo`).

### Issues

| Tool | Description |
|------|-------------|
| `get-issue` | Get a single issue by number, including title, body, state, labels, and assignees |
| `list-issues` | List open issues on a repository (excludes pull requests) |
| `create-issue` | Create a new issue with optional labels, assignees, and milestone |
| `update-issue` | Update an existing issue's title, body, state, labels, assignees, or milestone |
| `close-issue` | Close an issue with an optional reason (completed or not_planned) |

### Pull Requests

| Tool | Description |
|------|-------------|
| `get-pull-request` | Get a PR by number, including mergeable status and diff stats |
| `list-pull-requests` | List PRs, optionally filtered by state (open/closed/all) |
| `create-pull-request` | Create a new PR with head/base branches and optional draft mode |
| `update-pull-request` | Update a PR's title, body, state, or base branch |
| `merge-pull-request` | Merge a PR using merge, squash, or rebase strategy |
| `list-pull-request-files` | List files changed in a PR with additions, deletions, and status |
| `get-pull-request-diff` | Get the unified diff for a PR with line numbers |
| `create-pull-request-review` | Submit a review (approve, request changes, or comment) with optional inline comments |
| `request-reviewers` | Request reviewers by username or team slug |

### Comments

| Tool | Description |
|------|-------------|
| `list-comments` | List all comments on an issue or pull request |
| `create-comment` | Add a comment to an issue or pull request |

### Branches

| Tool | Description |
|------|-------------|
| `list-branches` | List all branches on a repository |
| `create-branch` | Create a new branch from a given SHA |

### Files

| Tool | Description |
|------|-------------|
| `get-file-contents` | Get file contents, SHA, and metadata from a repository |
| `get-repository-tree` | List files and directories in a repository tree |
| `create-or-update-file` | Create or update a file by committing directly to a branch |
| `delete-file` | Delete a file by committing the deletion to a branch |

### Labels

| Tool | Description |
|------|-------------|
| `list-labels` | List all labels defined on a repository |
| `list-issue-labels` | List all labels on a specific issue |
| `add-labels-to-issue` | Add one or more labels to an issue |
| `remove-label-from-issue` | Remove a label from an issue |
| `create-label` | Create a new label with name, color, and description |
| `delete-label` | Delete a label definition from a repository |

### CI/CD Status

| Tool | Description |
|------|-------------|
| `get-commit-status` | Get combined commit status for a ref (branch, tag, or SHA) |
| `list-check-runs` | List check runs (CI/CD checks) for a ref |

### Search

| Tool | Description |
|------|-------------|
| `search-code` | Search for code using GitHub search syntax |
| `search-issues` | Search for issues and pull requests using GitHub search syntax |

### Pageant Server (`/mcp/pageant`)

| Tool | Description |
|------|-------------|
| `create-work-item` | Create a work item from a GitHub issue, tracking it in Pageant |
| `delete-work-item` | Delete a work item created from a GitHub issue |
| `create-agent` | Create a new AI agent configured with tools, events, provider, and permissions |

---

## Webhook Events

Pageant handles the following GitHub webhook events and dispatches them to subscribed agents:

| Event | Laravel Event | Description |
|-------|--------------|-------------|
| `issues` | `GitHubIssueReceived` | Issue opened, closed, edited, labeled, etc. |
| `pull_request` | `GitHubPullRequestReceived` | PR opened, closed, synchronized, etc. |
| `issue_comment` | `GitHubCommentReceived` | Comment created, edited, or deleted on an issue/PR |
| `pull_request_review` | `GitHubPullRequestReviewReceived` | Review submitted on a PR |
| `push` | `GitHubPushReceived` | Push to a branch |
| `installation` | *(handled directly)* | App installed, uninstalled, suspended, unsuspended |

When an event is received:

1. Webhook signature is verified (HMAC-SHA256)
2. A Laravel event is dispatched
3. Listeners find agents subscribed to that event for the affected repo
4. Agent jobs are dispatched with event context

Additionally, `WorkItemCreated` is an internal event dispatched when a work item is created from a GitHub issue.

---

## Architecture

### Models

```
User
├── organizations [BelongsToMany] → Organization
└── currentOrganization [BelongsTo] → Organization

Organization
├── users [BelongsToMany] → User
├── repos [HasMany] → Repo
├── agents [HasMany] → Agent
├── skills [HasMany] → Skill
├── projects [HasMany] → Project
├── workItems [HasMany] → WorkItem
└── githubInstallation [HasOne] → GithubInstallation

Repo
├── organization [BelongsTo] → Organization
├── agents [BelongsToMany] → Agent
├── skills [BelongsToMany] → Skill
└── projects [BelongsToMany] → Project

Agent
├── organization [BelongsTo] → Organization
├── repos [BelongsToMany] → Repo
└── skills [BelongsToMany] → Skill

Project
├── organization [BelongsTo] → Organization
├── repos [BelongsToMany] → Repo
└── workItems [HasMany] → WorkItem

WorkItem
├── organization [BelongsTo] → Organization
└── project [BelongsTo] → Project
```

### Authentication Flow

1. User visits `/auth/github` and is redirected to GitHub OAuth
2. On callback, the user is created/updated and GitHub installations are synced to organizations
3. MCP clients authenticate via OAuth 2.1 (Passport) at the `/mcp` endpoint

### Agent Dispatch Flow

1. GitHub sends a webhook to `/webhooks/github`
2. The webhook controller verifies the signature and dispatches a Laravel event
3. Event listeners find agents subscribed to that event type for the affected repo
4. Agent jobs are dispatched with the event context (issue details, PR diff, commit info, etc.)

## License

Proprietary.
