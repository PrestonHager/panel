#!/usr/bin/env bash
# Copy updated Pterodactyl container Nix modules into /etc/nixos and rebuild.
set -euo pipefail

SRC="$(cd "$(dirname "$0")" && pwd)"
DEST="/etc/nixos/nixos/containers"

if [[ ! -d "$DEST" ]]; then
  echo "Error: $DEST does not exist." >&2
  exit 1
fi

for file in pterodactyl-docker.nix pterodactyl-test.nix pterodactyl.nix; do
  echo "Installing $file -> $DEST/$file"
  sudo install -m 0644 "$SRC/$file" "$DEST/$file"
done

echo
echo "Fixing test panel git remotes on host (HTTPS, no SSH)..."
if [[ -d /home/prestonh/Projects/panel/.git ]]; then
  git -C /home/prestonh/Projects/panel remote set-url origin https://github.com/PrestonHager/panel.git 2>/dev/null || true
  if git -C /home/prestonh/Projects/panel remote get-url fork &>/dev/null; then
    git -C /home/prestonh/Projects/panel remote set-url fork https://github.com/PrestonHager/panel.git
  fi
fi

echo
echo "Rebuilding NixOS (this rebuilds the pterodactyl OCI images)..."
sudo nixos-rebuild switch

echo
echo "Restarting test pod containers..."
sudo systemctl restart podman-pterodactyl-test.service podman-pterodactyl-test-db.service podman-pterodactyl-test-redis.service || true
sudo systemctl restart pterodactyl-test-env.service pterodactyl-test-panel-perms.service || true

echo
echo "Verify git works inside the test container:"
echo "  sudo podman exec pterodactyl-test git fetch origin feat/plugin-manager"
echo "  PANEL_CONTAINER=pterodactyl-test /home/prestonh/Projects/panel/scripts/panel-update-container.sh --check-only"
