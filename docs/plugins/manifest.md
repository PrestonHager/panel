# Plugin manifest (`plugin.json`)

Each plugin repository must include a `plugin.json` file at the repository root.

## Required fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Reverse-domain identifier, e.g. `com.example.dns` |
| `name` | string | Human-readable name |
| `version` | string | Semantic version (`1.0.0`) |
| `entry` | string | Fully-qualified entry class implementing `PluginInterface` |

## Optional fields

| Field | Type | Description |
|-------|------|-------------|
| `permissions` | string[] | Requested capabilities (see [permissions.md](permissions.md)) |
| `hooks` | object | Map of hook name → listener class |
| `config.schema` | object | JSON Schema describing admin configuration (documentation only in v1) |
| `requires.panelPluginApi` | string | Minimum panel plugin API version (e.g. `"2.0"`); semver `>=` check at install/enable |
| `clientPermissions` | object | Map of permission key → label for subusers and HTTP routes |
| `api.routes` | array | HTTP routes served under `/api/plugins/{id}` (requires `api.serve`) |
| `ui.server` | object | Client SPA tab: `path`, `name`, `bundle`, optional `permission` (requires `ui.register`) |

## Panel plugin API version

The panel exposes plugin API version **2.0** (`PanelPluginApi::VERSION`). Plugins that need v2 features should declare:

```json
"requires": { "panelPluginApi": "2.0" }
```

Install or enable is rejected when the required version is not satisfied.

## Example

```json
{
  "id": "com.example.dns",
  "name": "DNS Records",
  "version": "1.0.0",
  "entry": "Com\\Example\\Dns\\Plugin",
  "permissions": [
    "events.subscribe",
    "server.read",
    "allocation.read",
    "http.request",
    "server.metadata.write"
  ],
  "hooks": {
    "server.installed": "Com\\Example\\Dns\\Listeners\\OnServerInstalled"
  }
}
```

## Code layout

PSR-4 autoloading maps the entry class namespace to `src/`:

```
plugin.json
src/
  Plugin.php
  Listeners/
    OnServerInstalled.php
```

If `entry` is `Com\Example\Dns\Plugin`, classes under `Com\Example\Dns\` load from `src/`.

## v2 example (HTTP API + UI + client permissions)

```json
{
  "id": "com.example.dns",
  "name": "DNS Records",
  "version": "2.0.0",
  "entry": "Com\\Example\\Dns\\Plugin",
  "requires": { "panelPluginApi": "2.0" },
  "permissions": [
    "events.subscribe",
    "config.read",
    "api.serve",
    "ui.register",
    "server.read",
    "http.request"
  ],
  "clientPermissions": {
    "records.read": "View DNS records",
    "records.create": "Create DNS records"
  },
  "api": {
    "routes": [
      {
        "method": "GET",
        "path": "/servers/{server}/records",
        "handler": "Com\\Example\\Dns\\Http\\RecordsController@index",
        "permission": "records.read"
      }
    ]
  },
  "ui": {
    "server": {
      "path": "/dns",
      "name": "DNS",
      "bundle": "assets/dns.js",
      "permission": "records.read"
    }
  }
}
```

See [plugin-http-api.md](plugin-http-api.md), [plugin-client-permissions.md](plugin-client-permissions.md), and [plugin-ui.md](plugin-ui.md).
