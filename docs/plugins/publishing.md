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

## Installation URL

Admins can install using:

- `https://github.com/owner/repo`
- `owner/repo` shorthand

Optional **Branch or Tag** field defaults to `main`.

## Permission changes

If you add new permissions in a release, document them clearly. Admins must disable and reinstall (or use a future upgrade flow) to approve new capabilities.

## Trust

Only install plugins from sources you trust. The panel clones the repository and executes PHP code with approved permissions.
