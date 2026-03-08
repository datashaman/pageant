# Copilot Instructions for Pageant

## What Is This Project?

Pageant is a **Laravel 12 GitHub App integration platform** that connects GitHub repositories with AI agents via the **Model Context Protocol (MCP)**. It exposes 35+ MCP tools for repository operations (issues, PRs, branches, files, labels, CI status, search), handles real-time GitHub webhooks, and provides a web UI for managing agents, repositories, skills, and projects.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4+, Laravel 12 |
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
    Tools/           # AI tool wrappers around MCP tools
  Concerns/          # Shared PHP traits
  Events/            # Laravel events (GitHub webhook events, WorkItemCreated)
  Http/Controllers/  # Controllers (GitHubAuthController, GitHubWebhookController, ChatController)
  Jobs/              # Queued jobs (RunWebhookAgent)
  Listeners/         # Event listeners that dispatch agent jobs
  Mcp/
    Servers/         # MCP server definitions (GitHubServer, PageantServer)
    Tools/           # Individual MCP tool classes (35+ tools)
  Models/            # Eloquent models (User, Organization, Agent, Repo, Skill, Project, WorkItem, GithubInstallation)
  Providers/         # Service providers
  Actions/           # Form/auth actions (Fortify, custom)
  Services/          # Business logic
bootstrap/
  app.php            # Middleware, routing, exception config (no Kernel.php in Laravel 12)
  providers.php      # Service providers list
routes/
  web.php            # Auth + webhook + dashboard routes
  resources.php      # Resource routes
  settings.php       # Settings routes
  ai.php             # AI/chat routes
tests/
  Feature/           # Feature tests (20+ files)
  Unit/              # Unit tests
database/
  migrations/        # Schema migrations
  factories/         # Model factories
  seeders/           # Seeders
resources/views/
  components/        # Reusable Blade components
  layouts/           # app/auth layout templates
  livewire/          # Livewire component views
config/              # 13 config files
.github/
  workflows/
    tests.yml        # CI: runs Pest tests on PRs to main
    lint.yml         # CI: runs Pint lint check on PRs to main
  skills/            # Skill files for agent tasks
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
└── skills [BelongsToMany] → Skill

Repo
├── organization [BelongsTo] → Organization
├── agents [BelongsToMany] → Agent
└── projects [BelongsToMany] → Project

WorkItem
├── organization [BelongsTo] → Organization
└── project [BelongsTo] → Project
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
php artisan test --compact --filter=AgentCrud    # Filter by name
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

QUEUE_CONNECTION=database   # Background job queue
```

OAuth keys for Passport:

```bash
php artisan passport:keys
```

---

## Coding Conventions

### PHP

- PHP 8.4+ features: constructor property promotion, enums, named arguments, match expressions.
- Always use explicit return type declarations.
- Use curly braces for all control structures.
- Prefer PHPDoc blocks over inline comments.
- Enum keys use TitleCase.
- Use `config('key')` instead of `env()` outside config files.

### Laravel

- Use `php artisan make:` commands to scaffold new files; pass `--no-interaction`.
- Middleware is configured in `bootstrap/app.php` (not in `Kernel.php` — that file does not exist in Laravel 12).
- Prefer Eloquent relationships and eager loading; avoid `DB::` raw queries.
- Use Form Request classes for validation (not inline validation).
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
- If frontend changes are not visible, run `npm run build` or `composer run dev`.

---

## GitHub Webhook Flow

1. GitHub POSTs to `/webhooks/github`
2. `GitHubWebhookController` verifies HMAC-SHA256 signature using `GITHUB_WEBHOOK_SECRET`
3. A Laravel event is dispatched (e.g., `GitHubIssueReceived`)
4. Listeners find `Agent` models subscribed to that event for the affected repo
5. `RunWebhookAgent` jobs are dispatched to the queue with event context

Supported events: `issues`, `pull_request`, `issue_comment`, `pull_request_review`, `push`, `installation`

---

## Authentication Flow

1. User visits `/auth/github` → redirected to GitHub OAuth
2. Callback at `/auth/github/callback` → user created/updated, GitHub installations synced to organizations
3. MCP clients authenticate via OAuth 2.1 (Passport) at `/mcp` endpoints

---

## CI/CD Workflows

Both workflows trigger on PRs to `main`:

- **`tests.yml`**: Installs PHP 8.4 + SQLite, Composer deps, npm deps, builds assets, generates app key, runs `php artisan test`
- **`lint.yml`**: Installs PHP 8.4, Composer deps, runs `vendor/bin/pint --test`

**Common CI failures:**
- Missing `APP_KEY`: run `php artisan key:generate` (CI does `cp .env.example .env` first)
- Vite manifest error: run `npm run build` before tests
- Pint failure: run `vendor/bin/pint --dirty` to fix formatting, then commit
- Missing Passport keys: run `php artisan passport:keys` (not always needed in test env)

---

## MCP Servers

### GitHub Server (`/mcp/github`) — 32 tools
Requires `repo` parameter as `owner/repo`. Groups: Issues, Pull Requests, Comments, Branches, Files, Labels, CI/CD Status, Search.

### Pageant Server (`/mcp/pageant`) — 3 tools
- `create-work-item`: Bridge a GitHub issue to an internal WorkItem
- `delete-work-item`: Remove a WorkItem
- `create-agent`: Create a new AI Agent

---

## Skills (Domain-Specific Agent Guidance)

Activate the relevant skill when working in that domain:

- **`fluxui-development`** — Flux UI Free components (buttons, forms, modals, inputs, dropdowns)
- **`livewire-development`** — Livewire 4 components, `wire:*` directives, reactivity
- **`pest-testing`** — Pest 4 tests, assertions, datasets, mocking
- **`tailwindcss-development`** — Tailwind CSS v4 styles and utilities
- **`developing-with-fortify`** — Laravel Fortify authentication features

---

## Errors & Workarounds

| Error | Cause | Fix |
|-------|-------|-----|
| `ViteException: Unable to locate file in Vite manifest` | Assets not built | Run `npm run build` |
| `Personal access token not found` / Passport error | OAuth keys missing | Run `php artisan passport:keys` |
| Pint CI failure | PHP code style issues | Run `vendor/bin/pint --dirty` locally and commit |
| `APP_KEY` not set | `.env` not created | Run `cp .env.example .env && php artisan key:generate` |
| N+1 query warnings | Missing eager loading | Use `->with('relation')` on queries |
