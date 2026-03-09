# Copilot Instructions for Pageant

## What Is This Project?

Pageant is a **Laravel 12 GitHub App integration platform** that connects GitHub repositories with AI agents via the **Model Context Protocol (MCP)**. It exposes 70+ MCP tools across three servers (GitHub, Pageant, Worktree), handles real-time GitHub webhooks, provides work item tracking with automated status reconciliation, and offers a web UI for managing agents, repositories, skills, projects, and a built-in chat assistant.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.5+, Laravel 12 |
| Auth | Laravel Passport (OAuth 2.1), Laravel Fortify, Laravel Socialite (GitHub OAuth) |
| MCP | `laravel/mcp` v0, `laravel/ai` |
| Frontend | Livewire 4, Flux UI 2 (free), Tailwind CSS 4, Alpine.js, Vite 7 |
| Testing | Pest 4, PHPUnit 12, Mockery |
| Linting | Laravel Pint |
| Dev tools | Laravel Sail, Laravel Pail, Laravel Boost |
| Database | SQLite (default/tests), MySQL/PostgreSQL supported |

---

## Directory Structure

```
app/
  Ai/
    Agents/          # AI agent implementations (GitHubWebhookAgent, PageantAssistant)
    Tools/           # AI tool wrappers (70+ tools matching MCP tools)
    ToolRegistry.php # Central tool registry with metadata and grouping
    EventRegistry.php# Webhook event registry with actions and filters
  Concerns/          # Shared PHP traits (BelongsToUserOrganization, HasSource)
  Console/Commands/  # Artisan commands (ReconcileWorkItems)
  Events/            # Laravel events (GitHub webhook events, WorkItem events, Plan events)
  Http/Controllers/  # Controllers (GitHubAuthController, GitHubWebhookController, ChatController)
  Jobs/              # Queued jobs (RunWebhookAgent, ReconcileWorkItemStatuses)
  Listeners/         # Event listeners (SyncWorkItemStatus, auto-discovered)
  Mcp/
    Servers/         # MCP server definitions (GitHubServer, PageantServer, WorktreeServer)
    Tools/           # Individual MCP tool classes (57 tools)
  Models/            # Eloquent models (User, Organization, Agent, Repo, Skill, Project, WorkItem, Plan, GithubInstallation)
  Providers/         # Service providers
  Services/          # Business logic (GitHubService, WorktreeManager, WorkItemOrchestrator, RepoInstructionsService, SkillRegistryService)
bootstrap/
  app.php            # Middleware, routing, exception config (no Kernel.php in Laravel 12)
  providers.php      # Service providers list
routes/
  web.php            # Auth + webhook + dashboard routes
  resources.php      # Resource CRUD routes (Livewire)
  settings.php       # Settings routes
  console.php        # Scheduled tasks (hourly work item reconciliation)
  ai.php             # MCP server routes (GitHub, Pageant, Worktree servers)
tests/
  Feature/           # Feature tests (30+ files)
  Unit/              # Unit tests
database/
  migrations/        # Schema migrations
  factories/         # Model factories
  seeders/           # Seeders
resources/views/
  pages/             # Full-page Livewire components (dashboard, agents, repos, skills, projects, work-items)
  components/        # Reusable Blade components
  layouts/           # app/auth layout templates
config/              # Config files
.claude/skills/      # Domain-specific skill files for agent guidance
.github/
  workflows/
    tests.yml        # CI: runs Pest tests on PRs to main
    lint.yml         # CI: runs Pint lint check on PRs to main
```

---

## Key Models & Relationships

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

Agent
├── organization [BelongsTo] → Organization
├── repos [BelongsToMany] → Repo
├── skills [BelongsToMany] → Skill
├── tools — JSON array of tool identifiers
├── events — JSON array of event subscriptions
├── provider/model — LLM provider and model (model "inherit" displays as "Default")
└── permission_mode, max_turns, background, isolation

Repo
├── organization [BelongsTo] → Organization
├── agents [BelongsToMany] → Agent
├── skills [BelongsToMany] → Skill
├── projects [BelongsToMany] → Project
├── setup_script — Bash script run during worktree provisioning
└── inferProjectId() — Returns project ID if repo belongs to exactly one project

Skill
├── organization [BelongsTo] → Organization
├── agent [BelongsTo] → Agent (optional direct binding)
├── agents [BelongsToMany] → Agent
├── repos [BelongsToMany] → Repo
├── source/source_reference/source_url — Registry provenance
└── allowed_tools — JSON array of permitted tools

WorkItem
├── organization [BelongsTo] → Organization
├── project [BelongsTo] → Project
├── plans [HasMany] → Plan
└── status — open/closed, reconciled with GitHub issue state
```

---

## How to Run, Build, and Test

### Initial Setup

```bash
composer run setup
# Installs Composer & npm deps, generates .env, generates app key, migrates DB, builds assets
```

### Development Server

```bash
composer run dev
# Starts PHP server, queue listener, Pail log viewer, and Vite in parallel
```

### Running Tests

```bash
php artisan test --compact                       # All tests
php artisan test --compact --filter=testName     # Filter by name
```

### Linting

```bash
vendor/bin/pint --dirty --format agent   # Fix formatting on changed files (run after any PHP changes)
vendor/bin/pint --parallel --test        # Check only (used in CI)
```

### Building Frontend

```bash
npm run build   # Production build
npm run dev     # Watch mode
```

### Full CI Check (lint + tests)

```bash
composer run test
```

---

## Environment Variables

Copy `.env.example` to `.env`. Key variables:

```
APP_KEY=              # Generate: php artisan key:generate
DB_CONNECTION=sqlite  # Default

GITHUB_CLIENT_ID=           # GitHub App OAuth Client ID
GITHUB_CLIENT_SECRET=       # GitHub App OAuth Client Secret
GITHUB_APP_ID=              # GitHub App ID (numeric)
GITHUB_APP_PRIVATE_KEY_PATH=# Path to PEM private key (e.g. storage/github-app.pem)
GITHUB_WEBHOOK_SECRET=      # Webhook secret for HMAC-SHA256 signature verification

ALLOWED_EMAILS=             # Optional: comma-separated email allowlist to restrict login
SMITHERY_API_KEY=           # Optional: Smithery API key for skill registry search

QUEUE_CONNECTION=database   # Background job queue
```

OAuth keys for Passport:

```bash
php artisan passport:keys
```

---

## Coding Conventions

### PHP

- PHP 8.5+ features: constructor property promotion, enums, named arguments, match expressions.
- Always use explicit return type declarations.
- Use curly braces for all control structures.
- Prefer PHPDoc blocks over inline comments.
- Enum keys use TitleCase.
- Use `config('key')` instead of `env()` outside config files.

### Laravel

- Use `php artisan make:` commands to scaffold new files; pass `--no-interaction`.
- Middleware is configured in `bootstrap/app.php` (not in `Kernel.php` — that file does not exist in Laravel 12).
- **Event listeners are auto-discovered** via the `handle()` method type-hint — no `EventServiceProvider` or `Event::listen()` registration needed.
- **Console commands** in `app/Console/Commands/` are auto-registered — no Kernel or manual registration.
- Scheduled tasks are defined in `routes/console.php`.
- Prefer Eloquent relationships and eager loading; avoid `DB::` raw queries.
- All multi-tenant resources use the `BelongsToUserOrganization` trait with `forCurrentOrganization()` scope.
- Use named routes and the `route()` helper for URL generation.
- Use queued jobs (`ShouldQueue`) for time-consuming operations.
- When creating models, also create factories and seeders.

### Tests

- All tests use Pest 4.
- Create tests: `php artisan make:test --pest {Name}` (feature) or `--unit` for unit tests.
- Use model factories; check existing factory states before manually setting attributes.
- Run only the affected tests: `php artisan test --compact --filter=TestName`.
- Every change must include a new or updated test.
- Do NOT delete tests without approval.

### Frontend

- Use `<flux:*>` components for all UI (buttons, forms, modals, inputs, dropdowns).
- Livewire 4 for reactive PHP components; Alpine.js for client-side interactions.
- Tailwind CSS 4 for styling.
- Page context is passed via `data-chat-context` attribute (JSON) on the root div of each page.
- If frontend changes are not visible, run `npm run build` or `composer run dev`.

---

## Key Architecture Concepts

### Authentication & Authorization

1. User visits `/auth/github` → redirected to GitHub OAuth with `user:email` scope
2. Callback validates email against optional `ALLOWED_EMAILS` allowlist (case-insensitive, comma-separated)
3. User is created/updated, GitHub App installations are synced to organizations
4. **All GitHub API calls use installation tokens** (not user OAuth tokens) — `GitHubService` generates JWT with the app private key, exchanges for installation tokens (cached 3500s)
5. MCP clients authenticate via OAuth 2.1 (Passport) at `/mcp` endpoints

### Cross-Tenant Isolation

- `BelongsToUserOrganization` trait on all multi-tenant models
- `forCurrentOrganization(User?)` scope validates org belongs to user
- Chat controller verifies repo belongs to user's organizations
- Conversation ownership validated via `user_id`

### GitHub Webhook Flow

1. GitHub POSTs to `/webhooks/github`
2. `GitHubWebhookController` verifies HMAC-SHA256 signature
3. A Laravel event is dispatched (e.g., `GitHubIssueReceived`)
4. Listeners find agents subscribed to that event for the affected repo
5. `RunWebhookAgent` jobs are dispatched to the queue with event context

### Work Item Status Reconciliation

Work items track GitHub issues and keep status in sync:

- **Hourly scheduler**: `ReconcileWorkItemStatuses` job runs via `routes/console.php`
- **Page load sync**: Synchronous dispatch when viewing work items index
- **Manual sync button**: On work items page, triggers immediate reconciliation
- **Webhook-driven**: `SyncWorkItemStatus` listener updates on GitHub issue open/close/reopen events
- **Project inference**: `Repo::inferProjectId()` used when creating work items without explicit project

### Chat Assistant

- `ChatController` handles `POST /chat/stream` with SSE streaming
- Resolves repo context from page `data-chat-context` attributes
- Loads repo-level instructions via `RepoInstructionsService` from `CLAUDE.md`, `.github/copilot-instructions.md`, `AGENTS.md` (cached 1 hour, max 4000 chars)
- Eagerly persists user message before streaming (survives stream errors)
- Validates conversation ownership per user

### Worktree Management

- `WorktreeManager` creates bare clones and feature branches for work items
- Per-repo `setup_script` (on Repo model) runs during provisioning (300s timeout)
- `WorkItemOrchestrator` executes plans step-by-step using worktree drivers
- Worktrees cleaned up via `git worktree remove --force`

### Skill Registry

- Skills can be imported from public registries (official MCP Registry at `registry.modelcontextprotocol.io`, Smithery at `api.smithery.ai`)
- `SkillRegistryService` handles search across registries
- Browse and import via web UI at `/skills/registry`
- MCP tools: `search-registry-skills`, `import-registry-skill`
- Smithery requires `SMITHERY_API_KEY` env var

---

## MCP Servers

### GitHub Server (`/mcp/github`) — 32 tools
Requires `repo` parameter as `owner/repo`. Uses GitHub App installation tokens. Groups: Issues, Pull Requests, Comments, Branches, Labels, CI/CD Status, Search.

### Pageant Server (`/mcp/pageant`) — 23 tools
Organization-scoped operations. Groups: Repos, Projects, Work Items, Agents, Skills, Skill Registry.

### Worktree Server (`/mcp/worktree`) — 12 tools
Local file operations, shell commands, and git operations within a git worktree.

---

## Skills (Domain-Specific Agent Guidance)

Activate the relevant skill when working in that domain:

- **`fluxui-development`** — Flux UI Free components (buttons, forms, modals, inputs, dropdowns)
- **`livewire-development`** — Livewire 4 components, `wire:*` directives, reactivity
- **`pest-testing`** — Pest 4 tests, assertions, datasets, mocking
- **`tailwindcss-development`** — Tailwind CSS v4 styles and utilities
- **`developing-with-fortify`** — Laravel Fortify authentication features

---

## CI/CD Workflows

Both workflows trigger on PRs to `main`:

- **`tests.yml`**: Installs PHP 8.4 + SQLite, Composer deps, npm deps, builds assets, generates app key, runs `php artisan test`
- **`lint.yml`**: Installs PHP 8.4, Composer deps, runs `vendor/bin/pint --test`

---

## Errors & Workarounds

| Error | Cause | Fix |
|-------|-------|-----|
| `ViteException: Unable to locate file in Vite manifest` | Assets not built | Run `npm run build` |
| `Personal access token not found` / Passport error | OAuth keys missing | Run `php artisan passport:keys` |
| Pint CI failure | PHP code style issues | Run `vendor/bin/pint --dirty` locally and commit |
| `APP_KEY` not set | `.env` not created | Run `cp .env.example .env && php artisan key:generate` |
| N+1 query warnings | Missing eager loading | Use `->with('relation')` on queries |
