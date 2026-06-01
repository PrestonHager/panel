# Plugin development

## Nix shell

```bash
nix develop
php artisan migrate
php artisan test --filter=Plugin
```

The flake includes `git` for clone-based installer tests and PHP with required extensions.

## Local fixture plugin

Tests use `tests/Fixtures/plugins/test-plugin`. Install it programmatically:

```php
app(\Pterodactyl\Services\Plugins\PluginManager::class)
    ->installFromPath(base_path('tests/Fixtures/plugins/test-plugin'));
```

## Skeleton

Copy `plugins/_skeleton/` when starting a new plugin.

## Environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `PTERODACTYL_PLUGIN_INSTALL_TIMEOUT` | `120` | Git clone timeout (seconds) |
| `PTERODACTYL_PLUGIN_MAX_BYTES` | `10485760` | Max package size |
| `PTERODACTYL_PLUGIN_HTTP_TIMEOUT` | `30` | Outbound HTTP timeout |
| `PTERODACTYL_PLUGIN_HTTP_ALLOWED_HOSTS` | `api.cloudflare.com,api.porkbun.com` | Comma-separated host allowlist |

## Storage path

Installed plugins live at `storage/app/plugins/{plugin_id}/` (outside the public web root).
