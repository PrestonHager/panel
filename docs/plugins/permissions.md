# Plugin permissions

Plugins declare permissions in `plugin.json`. The panel stores the approved list and enforces it on every API call.

## Catalog

| Permission | Description |
|------------|-------------|
| `events.subscribe` | Required to register any `hooks` from the manifest |
| `settings.read` | Read `plugins.{plugin_id}.*` settings keys |
| `settings.write` | Write `plugins.{plugin_id}.*` settings keys |
| `server.read` | Read non-sensitive server metadata |
| `allocation.read` | Read IP/port allocations for servers |
| `server.metadata.write` | Write rows in `plugin_data` for servers |
| `http.request` | Outbound HTTP to allowlisted hosts |
| `activity.log` | Write `plugin:{id}:*` activity log events |

## Guidelines

- Request the **minimum** permissions required.
- DNS/firewall automation typically needs: `events.subscribe`, `server.read`, `allocation.read`, `http.request`, `server.metadata.write`.
- Do not request `http.request` unless the plugin calls external APIs.
- Changing permissions after release requires disable → reinstall so admins re-approve.

## HTTP allowlist

Configure allowed hosts via environment variable:

```bash
PTERODACTYL_PLUGIN_HTTP_ALLOWED_HOSTS=api.cloudflare.com,api.porkbun.com
```

See `config/pterodactyl.php` → `plugins.http.allowed_hosts`.
