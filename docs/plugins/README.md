# Pterodactyl Panel Plugins

The panel plugin system lets you extend Pterodactyl with **permission-gated PHP packages** installed from GitHub. Plugins cannot access the database directly—they interact with the panel through a controlled API (`PluginContext`).

## Security model

- **Deny by default**: every capability requires an explicit permission in `plugin.json`.
- **Admin approval**: permissions are shown during install/enable and stored on the plugin record.
- **Scoped storage**: settings keys must live under `plugins.{plugin_id}.*`.
- **No secrets in DTOs**: server reads omit daemon credentials.
- **HTTP allowlist**: outbound requests only reach hosts listed in `PTERODACTYL_PLUGIN_HTTP_ALLOWED_HOSTS`.

## Quick start

1. Copy [`plugins/_skeleton`](../../plugins/_skeleton) as a starting point.
2. Push your plugin to GitHub.
3. In the panel admin area, open **Plugins → Install Plugin** and paste the repository URL.
4. Review permissions on the plugin detail page and click **Enable**.

## Documentation

- [Manifest reference](manifest.md) — `plugin.json` fields
- [Permissions](permissions.md) — capability catalog
- [Plugin API](api.md) — `PluginContext` accessors
- [Plugin HTTP API](plugin-http-api.md) — `/api/plugins/{id}` routes
- [Plugin client permissions](plugin-client-permissions.md) — subuser grants
- [Plugin UI](plugin-ui.md) — server tabs and asset bundles
- [Theme API](theme-api.md) — design token overlays (API v2.1)
- [Plugin settings](settings.md) — `settings.json` schema-driven configuration UI
- [Event hooks](hooks.md) — server lifecycle hooks
- [Publishing](publishing.md) — repo layout and versioning
- [Development](development.md) — Nix shell and testing

Panel plugin API version: **2.1** (declare `requires.panelPluginApi` in manifest; **2.0** plugins remain compatible).
