# Design System — Laravel + Livewire + Flux UI + TailwindCSS v4 + Vite

You are building UI for a Laravel application using **Livewire** (single-file components with anonymous class syntax), **Flux UI Free**, **TailwindCSS v4**, and **Vite**.

All UI work must strictly follow this design system. Never invent new patterns — use only what is defined here.

---

## Stack Rules

- **Templates**: Blade only. No inline styles. No raw HTML outside of Blade components.
- **Styling**: TailwindCSS v4 utility classes only. No custom CSS unless adding a new design token to `resources/css/app.css` (see Design Tokens below). No hardcoded hex values, px values, or arbitrary Tailwind brackets like `text-[#abc123]` or `mt-[13px]` — use tokens.
- **Interactivity**: Livewire for server-driven reactivity. Alpine.js (`x-data`, `x-show`, `x-transition`) for lightweight client-side UI only.
- **Component library**: **Flux UI** for buttons, inputs, tables, modals, forms, and other UI primitives. Use `<flux:*>` components — do not create custom equivalents.
- **Assets**: All JS/CSS compiled through Vite. Never use a CDN link for anything already in the stack.

---

## Clarifications for AI Agents

| Do not assume | Actual in this project |
|---------------|------------------------|
| Livewire Volt | This project uses **regular Livewire** (class-based components). No Volt. |
| `tailwind.config.js` | Tailwind **v4** — config lives in `resources/css/app.css` via `@theme`. |
| Custom button/input components | Use **Flux UI** components (`flux:button`, `flux:input`, etc.). |
| `resources/views/livewire/` | Livewire page components live in `resources/views/pages/**/⚡*.blade.php` (the ⚡ prefix is Livewire v4's convention for single-file page components). |
| Session-based theme | Theme (light/dark/system) is stored in **localStorage** via Flux's `$flux.appearance`. |

---

## Design Tokens (`resources/css/app.css`)

All colours, fonts, spacing, radii, and shadows are defined in `resources/css/app.css` and nowhere else.

### Tailwind v4 — use `@theme` and `@layer theme`

```css
@theme {
    /* Zinc palette (neutral UI) */
    --color-zinc-50: #fafafa;
    --color-zinc-100: #f4f4f5;
    /* ... through zinc-950 */

    /* Slate for surfaces */
    --color-slate-50: #f8fafc;
    --color-slate-100: #f1f5f9;
    /* ... */

    /* Semantic tokens */
    --color-accent: var(--color-zinc-800);
    --color-primary: var(--primary);  /* from :root below */
}
```

### Light/dark variables (`:root` and `.dark`)

The `:root` and `.dark` blocks define semantic tokens (oklch) for background, foreground, card, primary, muted, border, input, ring. Use these via Flux and Tailwind's dark variant.

- **Page background**: `bg-zinc-50 dark:bg-zinc-900` or `bg-white dark:bg-zinc-800`
- **Card/panel background**: `bg-zinc-50 dark:bg-zinc-900` (sidebar/header)
- **Borders**: `border-zinc-200 dark:border-zinc-700`
- **Text**: `text-zinc-900 dark:text-zinc-100`, `text-zinc-500 dark:text-zinc-400` for muted

### Adding new tokens

Add new tokens inside `@theme { }` or `@layer base { :root { } }`. Never hardcode colours in components.

---

## Two Types of Components — Know the Difference

| Type | Location | Purpose |
|------|----------|---------|
| **Blade components** | `resources/views/components/` | Pure UI: empty-state, confirm-delete-modal, app-logo, etc. No PHP logic. Reused everywhere. |
| **Livewire page components** | `resources/views/pages/**/⚡*.blade.php` | Feature units with backend state and actions. Each maps to a route. |

### Rules

- Livewire page components handle **data and behaviour** — they should compose Flux components and Blade components rather than raw layout markup.
- Blade components handle **presentation** — they receive data as props/slots and render it.
- A Livewire page SHOULD compose Flux components and app Blade components: `<flux:table>`, `<x-empty-state>`, `<flux:button>`, etc.
- A Blade component must NEVER contain `wire:` directives or `@php` state blocks (except when the component file itself is a Livewire single-file component, e.g. `⚡chat-panel.blade.php`).

---

## Flux UI — Use It First

Flux UI Free is the primary component library. **Always prefer Flux components over custom HTML or custom Blade components.**

### Available Flux components (Free edition)

avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, otp-input, profile, radio, select, separator, skeleton, switch, text, textarea, tooltip

### Examples (from this project)

```blade
<flux:button href="{{ route('projects.index') }}" wire:navigate>Back</flux:button>
<flux:button variant="danger" wire:click="delete">Delete</flux:button>
<flux:heading size="xl">{{ $project->name }}</flux:heading>
<flux:field>
    <flux:label>Email</flux:label>
    <flux:input type="email" wire:model="email" />
    <flux:error name="email" />
</flux:field>
<flux:table :paginate="$this->projects">
    <flux:table.columns>...</flux:table.columns>
    <flux:table.rows>...</flux:table.rows>
</flux:table>
<flux:modal :name="$id">...</flux:modal>
```

**Note:** `:paginate` expects a paginated result from the Livewire component (e.g. `$this->projects`, `$this->agents`), typically from `WithPagination` or a `#[Computed]` property.

### Icons

Use [Heroicons](https://heroicons.com/) via Flux: `flux:icon.magnifying-glass`, `flux:icon.folder`, etc. For icons not in Heroicons, add via `php artisan flux:icon <name>`.

---

## App-Specific Blade Components

These exist in `resources/views/components/` and should be reused:

| Component | Purpose | Slots/Props |
|-----------|---------|-------------|
| `empty-state` | Empty state with icon, heading, description, optional CTA | `heading`, `description`, `icon`, `action` |
| `confirm-delete-modal` | Delete confirmation modal | `id`, `title`, `itemName`, `deleteMethod`, `deleteId` |
| `form-actions` | Form action buttons | slot |
| `show-header` | Page header with title and actions | slots |
| `auth-header`, `auth-session-status`, `action-message` | Auth flow UI | — |

### Component anatomy

- Use `@props` to declare accepted attributes.
- Use `{{ $attributes->merge(['class' => '...']) }}` to remain composable.
- Never hardcode colours — use `text-zinc-500`, `border-zinc-200`, `dark:border-zinc-600`, etc.
- Use Flux components inside Blade components when appropriate (e.g. `flux:heading`, `flux:text` in `empty-state`).

---

## Layout Structure

- **Main app layout**: `layouts/app/sidebar.blade.php` (sidebar + content) or `layouts/app/header.blade.php` (header + content).
- **Settings pages**: Use `<x-pages::settings.layout>` with `heading` and `subheading`. This Blade component lives at `resources/views/pages/settings/layout.blade.php` (the `pages` namespace is registered by Livewire v4).
- **Auth pages**: Use `layouts/auth/split`, `layouts/auth/card`, or `layouts/auth/simple`.
- **Page wrapper**: Content is rendered via `{{ $slot }}` in the layout.

### Spacing and width

- Content area: full width within the layout; use `space-y-6` or `gap-6` between sections.
- Sidebar: uses Flux's `flux:sidebar` (collapsible on mobile).

---

## Livewire Conventions

- **Location**: Page components live in `resources/views/pages/{resource}/⚡{action}.blade.php`.
- **Routing**: `Route::livewire('projects', 'pages::projects.index')` → `pages/projects/⚡index.blade.php`. `Route::livewire()` with the `pages::` namespace is the standard Livewire v4 pattern.
- **Component structure**: Single-file with anonymous class `new class extends Component { ... }` at top, then Blade template below.
- **State**: Public properties; use `#[Url]` for syncing to query string when appropriate.
- **Actions**: Public methods called via `wire:click`, `wire:submit`, etc.
- **Computed**: Use `#[Computed]` for derived data.
- **Pagination**: Use `WithPagination` trait; pass `:paginate="$this->items"` to `flux:table`.
- **Loading**: Use `wire:loading` with `opacity-50 pointer-events-none` on triggers when needed.
- **Include `@fluxScripts`** in the layout (already in `layouts/app/sidebar.blade.php` and `layouts/app/header.blade.php`). Flux styles are loaded via `@import` in `app.css`, so `@fluxStyles` is not used.

---

## Theme (Light / Dark / System)

### How it works

- Flux manages appearance via `$flux.appearance` (values: `light`, `dark`, `system`).
- Stored in **localStorage** under `flux-appearance`.
- The `@fluxAppearance` directive (in `partials/head.blade.php`) plus an inline script apply the `.dark` class on `<html>` before paint to prevent flash.
- On `system`, the class follows `prefers-color-scheme` at runtime.

### Toggle UI

Settings → Appearance uses `flux:radio.group` with `x-model="$flux.appearance"` and options: Light, Dark, System.

### Dark variant rules

Every component that uses background, border, or text colours must include `dark:` variants:

```blade
{{-- Correct --}}
<div class="bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700">

{{-- Wrong — light only --}}
<div class="bg-zinc-50 text-zinc-900">
```

---

## Color Vision (Colorblind) Support

The app supports `data-colorblind` on `<html>` with values: `deuteranopia`, `protanopia`, `tritanopia`.

- Stored in **localStorage** under `pageant-colorblind`.
- The inline script in `partials/head.blade.php` applies it on load.
- Settings → Appearance has a color vision selector.
- Custom overrides in `app.css` remap success/danger colours for deuteranopia/protanopia (e.g. success → blue, danger → amber).

---

## Typography

Use these consistently — never freestyle font sizes:

| Use | Class |
|-----|-------|
| Page title | `flux:heading size="xl"` or `size="2xl"` |
| Section heading | `flux:heading size="lg"` |
| Body / default | `flux:text` or `text-base text-zinc-900 dark:text-zinc-100` |
| Secondary / helper | `flux:text` with `text-sm text-zinc-500 dark:text-zinc-400` |
| Code / mono | `font-mono text-sm` |

---

## What to Never Do

- Hardcode any colour, font size, or spacing value
- Create custom button/input/table components when Flux provides them
- Use `style=""` attributes
- Use Tailwind `[]` arbitrary values for colours or spacing
- Put `wire:` directives or state logic inside pure Blade components
- Assume Volt — this project uses class-based Livewire
- Assume `tailwind.config.js` — this project uses Tailwind v4 with `@theme` in CSS
- Use `resources/views/livewire/` for pages — use `resources/views/pages/**/⚡*.blade.php`
- Write light-mode styles without their `dark:` counterparts
- Use session for theme — Flux uses localStorage
- Forget `@fluxScripts` in layouts that render Livewire/Flux components

---

## Exceptions

These cases are documented exceptions to the "no inline styles" and "no hardcoded colors" rules:

| Exception | Location | Reason |
|-----------|----------|--------|
| **Dynamic user/API colors** | Work items import modal (GitHub issue labels) | Label colors come from the GitHub API; acceptable to use inline `:style` for background/text when color is not known at build time. |
| **QR code invert** | Two-factor setup modal | `:style` for `filter: invert()` is required for dark-mode QR code legibility (QR codes are black-on-white and must be inverted when the UI is dark). |
| **x-cloak** | `action-message` component | Uses `x-cloak` with `[x-cloak] { display: none }` in app.css for initial hidden state before Alpine hydration — avoids `style="display: none"`. |
