# Pterodactyl Panel Style Guide

This guide documents the visual language of the Pterodactyl panel (client SPA and admin shell) and how plugin UI should align via the shared design token system.

Machine-readable tokens live in [`resources/design/tokens.json`](../../resources/design/tokens.json). Generated CSS variables are emitted to [`public/plugins/panel-tokens.css`](../../public/plugins/panel-tokens.css).

## Surfaces

| Surface | Where | Selector |
|---------|-------|----------|
| **Client** | React SPA (server/dashboard) | `:root`, `[data-pt-surface="client"]` |
| **Admin** | AdminLTE admin area | `[data-pt-surface="admin"]`, `.ptero-plugin--admin` |
| **Plugin host** | Plugin bundles | `.ptero-plugin` inside a surface wrapper |

The client SPA is **dark-only** (no global theme toggle). Admin uses the Pterodactyl dark AdminLTE skin (`skin-blue` + `pterodactyl.css` overrides).

---

## Client (React SPA)

### Source files

- [`tailwind.config.js`](../../tailwind.config.js) — color palette
- [`resources/scripts/assets/css/GlobalStylesheet.ts`](../../resources/scripts/assets/css/GlobalStylesheet.ts) — global body styles
- [`resources/scripts/components/elements/`](../../resources/scripts/components/elements/) — Button, Input, Switch primitives

### Palette

Custom HSL gray scale (`gray` / `neutral` in Tailwind):

| Token | Tailwind | Value |
|-------|----------|-------|
| Page background | `neutral-800` | `hsl(209, 20%, 25%)` |
| Card / row | `neutral-700` | `hsl(209, 18%, 30%)` |
| Input background | `neutral-600` | `hsl(209, 14%, 37%)` |
| Body text | `neutral-200` | `hsl(210, 16%, 82%)` |
| Muted text | `neutral-300`–`400` | `hsl(211, 13%, 65%)` |
| Primary action | `blue-600` / `primary-500` | `#2563eb` |
| Accent (nav active) | `cyan-600` | `#0891b2` |
| Danger | `red-600` | `#dc2626` |
| Success | `green-600` | `#16a34a` |

### Typography

- **Headings / nav:** IBM Plex Sans (`font-header`)
- **Body:** system sans-serif stack
- **Page title:** `text-2xl font-medium`
- **Section labels:** `text-xs uppercase text-neutral-300`
- **Body default:** `text-sm text-neutral-200` on `bg-neutral-800`

### Spacing and layout

- Content max width: **1200px** (`ContentContainer`)
- Card padding: `p-4`
- Button padding: `px-4 py-2` (default), `h-8` (small)
- Input padding: `p-3` (legacy) or `px-4 py-2` (InputField)
- Vertical rhythm between sections: `mb-4` / `1.25rem`

### Components

**Primary button:** blue background, light text, rounded corners, hover lightens.

**Secondary button:** gray background (`neutral-500` / `gray-500`).

**Danger button:** red background, light text.

**Input:** dark background, subtle border, focus ring `ring-blue-300` (4px).

**Switch:** toggle with `primary-500` when checked.

---

## Admin (AdminLTE)

### Source files

- [`resources/views/layouts/admin.blade.php`](../../resources/views/layouts/admin.blade.php)
- [`public/themes/pterodactyl/css/pterodactyl.css`](../../public/themes/pterodactyl/css/pterodactyl.css)

### Layout patterns

```html
<div class="box box-primary">
  <div class="box-header with-border">
    <h3 class="box-title">Title</h3>
  </div>
  <div class="box-body">...</div>
  <div class="box-footer">...</div>
</div>
```

Page header: `content-header` with `<h1>Title<small>subtitle</small></h1>` and `.breadcrumb`.

Forms: `.form-group` > `.control-label` + `.form-control`; help text in `.text-muted`.

### Palette

| Element | Color |
|---------|-------|
| Sidebar | `#181f27` |
| Header / logo | `#1f2933` |
| Content wrapper | `#33404d` |
| Box body | `#3f4d5a` |
| Body text | `#cad1d8` |
| Muted text | `#9aa5b1` |
| Active nav accent | `#099aa5` |
| Primary button | `#0967d3` |
| Link | `#007eff` |
| Form input bg | `rgba(81, 95, 108, 0.73)` |
| Form input border | `#606d7b` |

### Icons

Font Awesome 4 (`fa fa-*`) in sidebar and action buttons.

---

## Plugin UI

Plugin bundles use **`ptero-*`** classes from [`plugin-host.css`](../../public/plugins/plugin-host.css), which consume **`--pt-*`** CSS variables from `panel-tokens.css`.

### Do

- Let the host set `data-pt-surface="client"` or `data-pt-surface="admin"` on your mount root
- Use `ctx.getRootClass()` from `__PterodactylPluginContext` (returns `ptero-plugin`)
- Use `ptero-btn`, `ptero-input`, `ptero-plugin-box`, `ptero-table`, `ptero-alert--*`
- Load styles via the host (`ensurePluginHostStyles`) — do not duplicate hex colors

### Do not

- Detect admin theme via `body.skin-blue` or `prefers-color-scheme` in plugin bundles
- Hardcode colors that duplicate panel tokens
- Inject unapproved global CSS (use the [theme API](../plugins/theme-api.md) instead)

### Class reference

| Class | Purpose |
|-------|---------|
| `.ptero-plugin` | Root wrapper (inherits surface tokens) |
| `.ptero-plugin--admin` | Legacy admin alias (prefer host `data-pt-surface`) |
| `.ptero-plugin-box` | Card / section container |
| `.ptero-plugin-panel` | Nested panel (forms) |
| `.ptero-btn--primary` | Primary action |
| `.ptero-btn--secondary` | Secondary action |
| `.ptero-btn--danger` | Destructive action |
| `.ptero-input` / `.ptero-select` | Form controls |
| `.ptero-alert--danger` | Error alert |
| `.ptero-alert--success` | Success alert |
| `.ptero-alert--warning` | Warning alert |
| `.ptero-muted` | De-emphasized text |
| `.ptero-hint` | Field help text |

---

## Design tokens

Token keys use dot notation (e.g. `color.primary`). They map to CSS variables:

```
color.primary → --pt-color-primary
color.bg.surface → --pt-color-bg-surface
```

Plugins may contribute **approved** token overrides via `ui.theme` in `plugin.json`. See [theme-api.md](../plugins/theme-api.md).

---

## Related documentation

- [Plugin UI](../plugins/plugin-ui.md) — bundles, context, assets
- [Theme API](../plugins/theme-api.md) — global theme overlays
- [Settings GUI](../plugins/settings.md) — schema-driven plugin settings
