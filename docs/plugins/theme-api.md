# Plugin Theme API (Panel Plugin API v2.1)

Plugins can contribute **approved** design token overlays that adjust panel-wide CSS variables on the client SPA, admin shell, and plugin bundles.

Requires `requires.panelPluginApi` **2.1** or compatible when using host theme context helpers (`surface`, `tokens`, `getRootClass`).

## Permission

Request `ui.theme` in manifest `permissions`. The panel admin must explicitly approve which token keys and surfaces each plugin may override.

## Manifest

```json
{
  "permissions": ["ui.register", "ui.theme"],
  "ui": {
    "server": {
      "path": "/example",
      "name": "Example",
      "bundle": "assets/example.js",
      "permission": "records.read"
    },
    "theme": {
      "surfaces": ["client", "admin"],
      "tokens": {
        "color.primary": "#2563eb",
        "color.bg.surface": "hsl(209, 20%, 25%)"
      },
      "stylesheet": "assets/theme.css"
    }
  }
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `surfaces` | No (default `["client"]`) | Where overlays apply: `client` and/or `admin` |
| `tokens` | No | Map of whitelisted token keys to CSS values |
| `stylesheet` | No | Optional asset path; must only set `--pt-*` variables |

### Rules

- `ui.theme` requires the `ui.theme` permission.
- Token keys must appear in the design token whitelist (`resources/design/tokens.json`).
- Values are plain CSS color/size/font strings (no `url()` or expressions).
- `admin` surface requires explicit declaration in `surfaces`.
- Later enabled plugins (ordered by name) override the same token key.

## Admin approval

On **Admin → Plugins → View plugin**, the **Theme overlay** section lists declared tokens. Admins can:

1. Enable or disable the overlay for this plugin
2. Choose surfaces (`client`, `admin`)
3. Approve individual token keys

Approved settings are stored in `plugins.approved_theme`:

```json
{
  "enabled": true,
  "surfaces": ["client"],
  "token_keys": ["color.primary"]
}
```

Only approved keys from enabled plugins are merged into the runtime overlay.

## Runtime assets

| URL | Description |
|-----|-------------|
| `/plugins/panel-tokens.css` | Base `--pt-*` variables for client and admin |
| `/plugins/panel-theme.css` | Merged approved overlays (cached, regenerated on enable/disable/approval) |
| `/plugins/plugin-host.css` | Plugin UI components using `var(--pt-*)` |

Client SPA and admin plugin views load base tokens + overlay automatically.

## Host context (bundles)

`window.__PterodactylPluginContext` includes:

| Property | Type | Description |
|----------|------|-------------|
| `surface` | `'client' \| 'admin'` | Host-resolved UI surface |
| `tokens` | `Record<string, string>` | Resolved `--pt-*` values (keys without prefix, dots for segments) |
| `getRootClass()` | `() => string` | Root wrapper class (`ptero-plugin`) |
| `theme` | `'dark' \| 'light'` | Deprecated; use `surface` |

Mount roots receive `data-pt-surface="client"` or `"admin"` and class `ptero-plugin`.

### Vanilla JS example

```javascript
window.PterodactylPlugin_com_example_plugin = function () {
  var ctx = window.__PterodactylPluginContext;
  var root = document.getElementById('plugin-root-com.example.plugin');

  root.innerHTML =
    '<div class="' + ctx.getRootClass() + '">' +
    '<button class="ptero-btn ptero-btn--primary">Save</button>' +
    '</div>';
};
```

Do **not** detect AdminLTE `skin-blue` or `prefers-color-scheme` in plugin bundles; use `ctx.getRootClass()` and `ptero-*` classes.

## Token whitelist

Keys use dot notation in manifests and map to CSS variables:

| Manifest key | CSS variable |
|--------------|--------------|
| `color.primary` | `--pt-color-primary` |
| `color.bg.surface` | `--pt-color-bg-surface` |
| `radius.md` | `--pt-radius-md` |

See [`resources/design/tokens.json`](../../resources/design/tokens.json) and [`docs/design/style-guide.md`](../design/style-guide.md) for the full list and upstream sources.

## Plugin UI classes

Use [`plugin-host.css`](../../public/plugins/plugin-host.css) classes (`ptero-btn`, `ptero-input`, `ptero-alert`, etc.). They consume `--pt-*` variables and track approved overlays automatically.

Optional helper: `/plugins/plugin-ui.js` exposes `PterodactylPluginUi.enrichContext()` for admin Blade mounts.

## Activity log

Approving or updating theme settings logs `plugin:theme.approved`.
