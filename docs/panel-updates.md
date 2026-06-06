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

| Setting | Description |
|---------|-------------|
| Repository | GitHub slug such as `pterodactyl/panel` or `myuser/my-fork` |
| Mode | `auto`, `release`, or `git` |
| Branch | Git branch/ref for git mode |
| Release | Optional pinned version for release mode |
| Git remote | Remote name used for git fetch (default `origin`) |

Environment defaults live in `config/pterodactyl.php` under `update.*` and can be overridden in the settings table when `APP_ENVIRONMENT_ONLY=false`.

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

Docker-based installations should continue using image updates (`docker compose pull`) rather than this in-panel updater. Mount persistent volumes for `/app/var`, `/app/storage`, and database data before upgrading images.

## Wings

Panel upgrades do not update Wings. See the [Wings upgrade guide](https://pterodactyl.io/wings/1.0/upgrading.html).
