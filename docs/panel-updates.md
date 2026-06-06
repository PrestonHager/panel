# Panel Safe Updater

The panel includes a safe self-update workflow with automatic backups, optional operator download bundles, configurable upstream repositories, and rollback support.

## Admin UI

Open **Admin → Settings → Updates** to:

- Configure the upstream GitHub repository (`owner/repo`, including forks)
- Choose update mode: `auto`, `release`, or `git`
- Download an optional configuration bundle (`.env`, settings export, plugin list)
- Queue a safe upgrade with automatic backup and progress polling
- Restore from previous automatic backups

## CLI

Recommended command:

```bash
php artisan p:upgrade:safe
```

Wrapper script:

```bash
bash scripts/panel-update.sh
```

Legacy command (no automatic backup):

```bash
php artisan p:upgrade
```

## Upstream configuration

Open **Admin → Settings → Updates** to configure where the panel checks for and pulls updates.

| Setting | Description |
|---------|-------------|
| Repository | GitHub slug such as `pterodactyl/panel` or `PrestonHager/panel` for a fork |
| Mode | `auto`, `release`, or `git` |
| Branch | Git branch/ref used for commit checks and git upgrades (for example `feat/plugin-manager`) |
| Release | Optional pinned version for release tarball mode |
| Git remote | Remote name used for git fetch (default `origin`) |

Environment defaults live in `config/pterodactyl.php` under `update.*` and can be overridden in the settings table when `APP_ENVIRONMENT_ONLY=false`:

| Environment variable | Setting key |
|---------------------|-------------|
| `PTERODACTYL_UPDATE_REPOSITORY` | Repository |
| `PTERODACTYL_UPDATE_MODE` | Mode |
| `PTERODACTYL_UPDATE_BRANCH` | Branch |
| `PTERODACTYL_UPDATE_RELEASE` | Pinned release |
| `PTERODACTYL_UPDATE_GIT_REMOTE` | Git remote |

### How updates are detected

1. **Release version** — compares `config('app.version')` to the latest GitHub release on the configured repository.
2. **Commit SHA** — when running from git (or mode is `git`), compares your installed commit to the HEAD of the configured branch. This detects new pushes even when the manifest version is unchanged (common on forks and feature branches).

After a successful git upgrade, the panel stores the installed commit SHA so tarball-only installs can still participate in commit tracking when a `.git` directory is present.

## Automatic backups

Before each safe upgrade, the panel stores a backup under:

```
storage/app/panel-upgrades/backups/{uuid}/
```

Contents:

- `database.sql.gz` (MySQL/MariaDB via `mysqldump`)
- `.env`
- `storage-app.tar.gz`
- `config-app.php`
- `manifest.json`

The five most recent backups are retained by default.

## Optional download bundle

The admin UI can generate a lighter zip containing:

- `.env`
- exported panel settings
- installed plugin summary
- upgrade checklist

This is optional. Declining it does not skip the automatic pre-update backup.

## Failure handling

If migration or post-update steps fail during a safe upgrade, the panel attempts to restore the automatic backup created for that run and writes a log file to:

```
storage/logs/panel-upgrade-YYYY-mm-dd-HHMMSS.log
```

## Docker deployments

### Environment-only vs admin settings

Docker images often ship with `APP_ENVIRONMENT_ONLY=true`. In that mode the panel **does not load** upstream settings from the database, so **Admin → Settings → Updates** cannot save changes until you set:

```bash
APP_ENVIRONMENT_ONLY=false
```

in `/app/var/.env` (the mounted env file in the official image) or in your Podman/Compose `environment:` block.

If you prefer GitOps-style configuration, leave `APP_ENVIRONMENT_ONLY=true` and set upstream values entirely via environment variables (see table above).

### Recommended approaches

| Deployment | How to update |
|------------|----------------|
| **Official image** (`ghcr.io/pterodactyl/panel`) | `podman pull` / `docker compose pull`, recreate the container. Code lives in the image, not a volume. |
| **Custom fork image** | Build and push from your fork CI, then pull + recreate on the host. |
| **Bind-mounted git checkout** | Configure upstream env vars, then run `php artisan p:upgrade:safe` inside the container (git mode). |
| **Release tarball in container** | Set `PTERODACTYL_UPDATE_MODE=release` and ensure your fork publishes `panel.tar.gz` release assets. |

Stock container images do **not** include a `.git` directory. Commit-based detection and git upgrades require either a bind-mounted repository or `PTERODACTYL_UPDATE_MODE=release`.

Mount persistent volumes for at least:

- `/app/var` (`.env`, generated state)
- `/app/storage` (logs, uploads, plugin data, upgrade backups)
- Database data (external MariaDB/PostgreSQL volume)

Without mounting `/app` source code, in-container upgrades modify the container filesystem and are **lost** on the next `podman run` unless you commit to the same container layer.

### Container helper script

From the host (NixOS, etc.), after setting `PANEL_CONTAINER`:

```bash
export PANEL_CONTAINER=pterodactyl-panel

# Check only (exit 1 when an update is available)
./scripts/panel-update-container.sh --check-only

# Run safe upgrade when an update is detected
./scripts/panel-update-container.sh

# Always run the upgrader (manual pull)
./scripts/panel-update-container.sh --force
```

Inside the container you can also run:

```bash
php artisan p:upgrade:check
php artisan p:upgrade:check --json
php artisan p:upgrade:safe --no-interaction
```

### Example Podman Compose environment

```yaml
environment:
  APP_ENVIRONMENT_ONLY: "false"
  PTERODACTYL_UPDATE_REPOSITORY: PrestonHager/panel
  PTERODACTYL_UPDATE_BRANCH: feat/plugin-manager
  PTERODACTYL_UPDATE_MODE: git
  PTERODACTYL_UPDATE_GIT_REMOTE: origin
```

For a host-level scheduled update on NixOS, use a `systemd` timer or cron that runs `panel-update-container.sh --check-only` and only calls the script without `--check-only` when the check exits `1`.

Docker-based installations using only the official image should continue using **image updates** (`podman compose pull`) rather than the in-panel updater when `/app` is not bind-mounted.

## Wings

Panel upgrades do not update Wings. See the [Wings upgrade guide](https://pterodactyl.io/wings/1.0/upgrading.html).
