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

## Shared panel stylesheet

The panel ships `/plugins/plugin-host.css` with `ptero-*` classes that match client button/input
styling (blue primary buttons, neutral inputs). The client SPA loads this automatically before
your bundle; admin plugin views should include the same file:

```html
<link rel="stylesheet" href="{{ asset('plugins/plugin-host.css') }}">
```

Use classes such as `ptero-btn ptero-btn--primary`, `ptero-btn--secondary`, `ptero-btn--danger`,
`ptero-input`, `ptero-select`, `ptero-plugin-box`, and `ptero-plugin--on-light` (auto-detected
when `body.skin-blue` is present for AdminLTE).

## Bundle contract

Expose a global mount function:

```javascript
window.PterodactylPlugin_com_example_dns = function () {
  // render into #plugin-root-com.example.dns or use __PterodactylPluginContext
};
```

`window.__PterodactylPluginContext` provides:

- `pluginId`
- `serverUuid`
- `apiBase` (e.g. `/api/plugins/com.example.dns`)
- `csrfToken` (required for session-authenticated POST/PUT/DELETE; admin views pass this from Blade)
- `getPermissions()`
- `hasFullAccess()` (optional; returns true for server owners / root admins with `*` core access)

Mutating API calls must send `X-CSRF-TOKEN` and `X-Requested-With: XMLHttpRequest` with `credentials: 'same-origin'`.
