#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "Pterodactyl Panel Safe Updater"
echo "=============================="
echo

if ! command -v php >/dev/null 2>&1; then
    echo "Error: php is not installed or not in PATH." >&2
    exit 1
fi

if ! php artisan --version >/dev/null 2>&1; then
    echo "Error: unable to run artisan from ${ROOT_DIR}." >&2
    exit 1
fi

echo "Optional pre-checks:"
if command -v mysqldump >/dev/null 2>&1; then
    echo "  mysqldump: found"
else
    echo "  mysqldump: not found (automatic DB backup will fail unless using external backups)"
fi

if command -v git >/dev/null 2>&1; then
    echo "  git: found"
else
    echo "  git: not found (git-based upgrades will not be available)"
fi

echo
echo "Starting safe upgrade via php artisan p:upgrade:safe"
echo

if php artisan p:upgrade:safe "$@"; then
    echo
    echo "Upgrade completed successfully."
    exit 0
fi

echo
echo "Upgrade failed."
echo "If rollback was attempted, review storage/logs/panel-upgrade-*.log"
echo "You can also restore manually from Admin -> Settings -> Updates."
exit 1
