# Plugin client UI (`ui.client`)

Dashboard-level plugin tabs extend the client SPA outside the server context.

## Manifest

```json
{
  "permissions": ["ui.register", "ui.client.register"],
  "ui": {
    "client": {
      "path": "/store",
      "name": "Store",
      "bundle": "assets/store.js",
      "exact": true
    }
  }
}
```

## Discovery

`GET /api/client/plugins/enabled`:

```json
{
  "data": {
    "server": [ ... ],
    "client": [
      {
        "id": "com.example.store",
        "ui": {
          "client": {
            "path": "/store",
            "name": "Store",
            "bundle": "assets/store.js"
          }
        }
      }
    ]
  }
}
```

## Mount contract

Same as [plugin-ui.md](plugin-ui.md) server tabs:

- Load `/plugins/panel-tokens.css`, `/plugins/panel-theme.css`, `/plugins/plugin-host.css`
- Expose `window.PterodactylPlugin_{sanitized_id}()`
- Use `window.__PterodactylPluginContext` (`apiBase`, `csrfToken`, `getRootClass()`, `tokens`)

Mount root: `#plugin-root-{pluginId}` (dots in plugin ID).

When `ui.client.path` is `/`, the plugin replaces the default dashboard home route.
