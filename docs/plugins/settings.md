# Plugin settings (`settings.json`)

Plugins may ship a `settings.json` file at the repository root to declare structured settings editable in the admin panel and/or client server interface.

## File location

```
my-plugin/
  plugin.json
  settings.json
  src/
```

## Schema

```json
{
  "fields": [
    {
      "key": "api_token",
      "type": "password",
      "label": "API Token",
      "description": "Write-only secret stored in encrypted plugin config",
      "surfaces": ["admin"],
      "storage": "config",
      "required": true,
      "sensitive": true,
      "audit": true
    },
    {
      "key": "enabled_profiles",
      "type": "multiselect",
      "label": "Enabled profiles",
      "surfaces": ["client"],
      "storage": "server",
      "permission": "settings.manage",
      "options": [
        { "value": "minecraft-java", "label": "Minecraft Java" }
      ]
    },
    {
      "key": "srv_profiles",
      "type": "list",
      "label": "SRV Profiles",
      "itemLabel": "Profile",
      "surfaces": ["admin"],
      "storage": "config",
      "default": [],
      "itemFields": [
        { "key": "id", "type": "string", "label": "Profile ID", "required": true },
        { "key": "label", "type": "string", "label": "Display Label" }
      ]
    }
  ]
}
```

## Field properties

| Property | Description |
|----------|-------------|
| `key` | Storage key |
| `type` | `string`, `text`, `password`, `boolean`, `integer`, `number`, `select`, `multiselect`, `json`, `list` |
| `label` | Display label |
| `description` | Help text |
| `surfaces` | `admin`, `client`, or both |
| `storage` | `config`, `server`, or `global` |
| `permission` | Required client permission for client-visible fields |
| `required`, `default`, `options`, `min`, `max`, `placeholder` | Validation and UI metadata |
| `sensitive` | Mask value in admin UI; never expose via client API |
| `audit` | Log setting changes without values |
| `ownerOnly` | Restrict client field to server owner / root admin |

### List fields

Use `type: "list"` for ordered collections of objects (for example SRV profile definitions).

| Property | Description |
|----------|-------------|
| `itemFields` | Nested field definitions for each row (required) |
| `itemLabel` | Row label prefix shown in the UI (for example `Profile` → "Profile #1") |
| `minItems` / `maxItems` | Optional bounds on row count |
| `default` | Default array value (usually `[]`) |

Each row is stored as an object in the backing array. The admin and client settings UIs provide **Add**, **Remove**, **Up**, and **Down** controls for each list.

Nested `list` fields inside `itemFields` are not supported in v1.

Supported item field types: `string`, `text`, `password`, `boolean`, `integer`, `number`, `select`, `multiselect`, `json`.

## Storage backends

| Storage | Where values live | Typical surface |
|---------|-------------------|-----------------|
| `config` | Encrypted `plugins.config` | Admin only |
| `server` | `plugin_data` per server | Client (server tab) |
| `global` | Panel `settings` table (`plugins.{id}.*`) | Client (global) |

## Rules

- `storage: config` fields may only use `surfaces: ["admin"]`.
- Client-visible fields must declare a `permission` listed in `plugin.json` → `clientPermissions`.
- If `settings.json` is missing, the panel falls back to `plugin.json` → `config.schema` (admin-only).
- If neither exists, admins edit raw JSON.

## Client API

| Method | Route |
|--------|-------|
| GET | `/api/client/servers/{server}/plugins/{plugin}/settings` |
| PATCH | `/api/client/servers/{server}/plugins/{plugin}/settings` |
| GET | `/api/client/plugins/{plugin}/settings` |
| PATCH | `/api/client/plugins/{plugin}/settings` |

## Client UI

When a plugin exposes client settings, the panel adds a **Settings** tab at `{ui.server.path}/settings` in the server navigation.

See also [manifest.md](manifest.md) and [plugin-client-permissions.md](plugin-client-permissions.md).
