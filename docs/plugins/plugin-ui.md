# Plugin UI (server tabs)

Plugins may register a server tab without modifying core panel React routes.

## Manifest

```json
{
  "permissions": ["ui.register"],
  "clientPermissions": {
    "records.read": "View records"
  },
  "ui": {
    "server": {
      "path": "/dns",
      "name": "DNS",
      "bundle": "assets/dns.js",
      "permission": "records.read",
      "exact": true
    }
  }
}
```

## Bootstrap

`GET /api/client/plugins/enabled` returns enabled plugins with UI metadata.

## Assets

Bundles are served from:

```
/plugins-assets/{plugin_id}/{bundle}
```

Files are read from `storage/app/plugins/{plugin_id}/` with path traversal protection.

## Design tokens and stylesheets

The panel ships shared CSS that keeps plugin UIs aligned with the client SPA and admin shell:

| Asset | Purpose |
|-------|---------|
| `/plugins/panel-tokens.css` | Base `--pt-*` design tokens |
| `/plugins/panel-theme.css` | Admin-approved token overlays |
| `/plugins/plugin-host.css` | `ptero-*` component classes |
| `/plugins/plugin-ui.js` | Optional context helpers |

The client SPA loads base tokens and overlays on startup. Plugin server tabs load all four before your bundle. Admin plugin views should include the same stylesheets:

```html
<link rel="stylesheet" href="/plugins/panel-tokens.css">
<link rel="stylesheet" href="/plugins/panel-theme.css">
<link rel="stylesheet" href="/plugins/plugin-host.css">
<script src="/plugins/plugin-ui.js"></script>
```

Wrap content in the host root class from context. Use `ptero-btn`, `ptero-input`, `ptero-select`, `ptero-plugin-box`, and related classes — they read `var(--pt-*)` and follow approved theme overlays.

**Do not** detect AdminLTE `body.skin-blue` or `prefers-color-scheme` in bundles. The host sets `data-pt-surface="client"` or `"admin"` on the mount root.

For global theme contributions from plugins, see [theme-api.md](theme-api.md).

## Bundle contract

Expose a global mount function:

```javascript
window.PterodactylPlugin_com_example_dns = function () {
  var ctx = window.__PterodactylPluginContext;
  var root = document.getElementById('plugin-root-com.example.dns');

  root.innerHTML = '<div class="' + ctx.getRootClass() + '">...</div>';
};
```

`window.__PterodactylPluginContext` provides:

- `pluginId`
- `serverUuid`
- `apiBase` (e.g. `/api/plugins/com.example.dns`)
- `csrfToken` (required for session-authenticated POST/PUT/DELETE; admin views pass this from Blade)
- `surface` — `'client'` or `'admin'`
- `tokens` — resolved design token values
- `getRootClass()` — wrapper class for your markup (`ptero-plugin`)
- `getPermissions()`
- `hasFullAccess()` (optional; returns true for server owners / root admins with `*` core access)
- `theme` — deprecated (`'dark'` / `'light'`); use `surface`

Mutating API calls must send `X-CSRF-TOKEN` and `X-Requested-With: XMLHttpRequest` with `credentials: 'same-origin'`.
