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
