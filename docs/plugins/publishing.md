# Publishing a plugin

## Repository layout

```
my-plugin/
  plugin.json
  README.md
  src/
    Plugin.php
    Listeners/
```

## Versioning

- Use semantic versioning in `plugin.json` → `version`.
- Tag releases in GitHub; admins may install a specific ref (branch/tag).

The panel detects outdated plugins using, in order:

1. **Release version** — compares `plugin.json` version to the latest GitHub release tag.
2. **Commit SHA** — when the version matches (or no release exists), compares the installed ref’s HEAD to the upstream branch configured in **Branch or Tag**.
3. **Content hash** — when commits match but files were copied or edited locally, compares a hash of installed plugin files to the upstream tarball.

After install or update, the panel stores `commit_sha` and `content_hash` on the plugin record. Admins can upgrade from **Plugins → Manage → Update from GitHub**, bulk-upgrade outdated plugins, or use **Upgrade All Outdated** on the plugin list.

## Installation URL

Admins can install using:

- `https://github.com/owner/repo`
- `owner/repo` shorthand

Optional **Branch or Tag** field defaults to `main`.

## Permission changes

If you add new permissions in a release, document them clearly. Admins must disable and reinstall (or use a future upgrade flow) to approve new capabilities.

## Trust

Only install plugins from sources you trust. The panel clones the repository and executes PHP code with approved permissions.
