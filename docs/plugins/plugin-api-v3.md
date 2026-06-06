# Panel Plugin API v3

Panel plugin API **3.0** extends v2.1 with dashboard UI, public/admin plugin routes, user and server provisioning accessors, plugin-owned migrations, and a block/keybind registry.

v2.x plugins remain compatible. Declare `"requires": { "panelPluginApi": "3.0" }` only when using v3 features.

## New manifest fields

### `ui.client`

Dashboard-level client SPA tab (mounted in `DashboardRouter`):

```json
"ui": {
  "client": {
    "path": "/",
    "name": "Home",
    "bundle": "assets/runtime.js",
    "permission": "builder.view",
    "exact": true
  }
}
```

Requires permissions: `ui.register`, `ui.client.register`.

`permission` is optional; when omitted the tab is visible to all authenticated users.

### `ui.admin`

Admin-area designer tab:

```json
"ui": {
  "admin": {
    "path": "/designer",
    "name": "Designer",
    "bundle": "assets/designer.js"
  }
}
```

Open at **Admin → Plugins → View plugin → Designer** (`/admin/plugins/view/{id}/designer`).

Admin API base: `/api/plugins-admin/{plugin_id}`.

### `ui.blocks` and `ui.keybinds`

Register block types and keybind actions for the block designer runtime. Requires `ui.blocks.register`.

### `api.routes[].auth`

| Value | Endpoint prefix | Auth |
|-------|-----------------|------|
| `client` (default) | `/api/plugins/{id}` | Client session / API key |
| `public` | `/api/plugins-public/{id}` | None (rate limited) |
| `admin` | `/api/plugins-admin/{id}` | Admin session |

### `migrations`

Array of migration class names (plugin namespace). Run on enable, rolled back on disable:

```json
"migrations": ["Com\\Example\\Plugin\\Migrations\\CreateTables"]
```

Implement `Pterodactyl\Plugins\Contracts\PluginMigrationInterface`.

## New permissions

| Permission | Description |
|------------|-------------|
| `user.read` | Read non-sensitive user profiles |
| `user.create` | Create users (signup flows) |
| `server.create` | Provision servers for users |
| `server.delete` | Delete owned servers |
| `ui.client.register` | Register `ui.client` tabs |
| `ui.blocks.register` | Register blocks/keybinds |
| `blocks.embed` | Allow embedding blocks in layouts |

## PluginContext accessors

| Accessor | Permission |
|----------|------------|
| `users()` | `user.read`, `user.create` |
| `provisioning()` | `server.create`, `server.delete` |
| `data()` | `server`, `user`, and `global` subject types |

## Client API

| Method | Route |
|--------|-------|
| GET | `/api/client/plugins/enabled` — `{ server: [], client: [] }` |
| GET | `/api/client/plugins/blocks` — block and keybind catalog |

## Design tokens

Additional whitelisted tokens for layout (see `resources/design/tokens.json`):

- `layout.sidebar.width`, `layout.content.maxWidth`, `layout.nav.height`
- `spacing.section`, `shadow.card`

See also [blocks.md](blocks.md) and [client-ui.md](client-ui.md).
