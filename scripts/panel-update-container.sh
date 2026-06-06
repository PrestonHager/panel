#!/usr/bin/env bash
# Run the panel safe updater inside a Docker/Podman container.
#
# Typical usage on a host (NixOS, etc.):
#   PANEL_CONTAINER=pterodactyl-panel ./scripts/panel-update-container.sh
#   PANEL_CONTAINER=pterodactyl-panel ./scripts/panel-update-container.sh --check-only
#
# Required container setup:
#   - Panel code must be upgradable in-place (git checkout bind-mounted, or release tarball mode).
#   - Stock images bake code into the image layer; prefer `podman pull` + recreate unless /app is mounted.
#   - Set upstream env vars in /app/var/.env or container environment (see docs/panel-updates.md).
set -euo pipefail

CONTAINER="${PANEL_CONTAINER:-${PODMAN_CONTAINER:-${DOCKER_CONTAINER:-}}}"
RUNTIME=""

if command -v podman >/dev/null 2>&1; then
    RUNTIME="podman"
elif command -v docker >/dev/null 2>&1; then
    RUNTIME="docker"
else
    echo "Error: neither podman nor docker was found in PATH." >&2
    exit 1
fi

if [[ -z "${CONTAINER}" ]]; then
    echo "Error: set PANEL_CONTAINER to the running panel container name or ID." >&2
    echo "Example: PANEL_CONTAINER=pterodactyl-panel $0" >&2
    exit 1
fi

CHECK_ONLY=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --check-only)
            CHECK_ONLY=true
            ;;
        --force)
            FORCE=true
            ;;
        -h|--help)
            sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            exit 1
            ;;
    esac
done

if ! "${RUNTIME}" inspect "${CONTAINER}" >/dev/null 2>&1; then
    echo "Error: container '${CONTAINER}' was not found (${RUNTIME})." >&2
    exit 1
fi

run_artisan() {
    "${RUNTIME}" exec -u root "${CONTAINER}" php /app/artisan "$@"
}

echo "Panel container updater (${RUNTIME}:${CONTAINER})"
echo "================================================"

echo "Checking upstream status..."
set +e
CHECK_OUTPUT="$(run_artisan p:upgrade:check --json 2>&1)"
CHECK_EXIT=$?
set -e

if [[ ${CHECK_EXIT} -gt 1 ]]; then
    echo "${CHECK_OUTPUT}" >&2
    echo "Error: upgrade check failed." >&2
    exit 1
fi

echo "${CHECK_OUTPUT}" | php -r '
$json = json_decode(stream_get_contents(STDIN), true);
if (!is_array($json)) {
    fwrite(STDERR, "Error: could not parse upgrade check JSON.\n");
    exit(2);
}
printf(
    "  repository: %s\n  branch: %s\n  method: %s\n  update_available: %s\n",
    $json["repository"] ?? "?",
    $json["branch"] ?? "?",
    $json["check_method"] ?? "?",
    !empty($json["update_available"]) ? "yes" : "no"
);
if (!empty($json["latest_version"])) {
    printf("  latest_release: %s\n", $json["latest_version"]);
}
if (!empty($json["remote_commit"])) {
    printf("  remote_commit: %s\n", substr($json["remote_commit"], 0, 12));
}
if (!empty($json["installed_commit"])) {
    printf("  installed_commit: %s\n", substr($json["installed_commit"], 0, 12));
}
'

if [[ "${CHECK_ONLY}" == "true" ]]; then
    exit "${CHECK_EXIT}"
fi

if [[ ${CHECK_EXIT} -eq 0 && "${FORCE}" != "true" ]]; then
    echo "No update available. Use --force to run the upgrader anyway."
    exit 0
fi

echo
echo "Starting safe upgrade (php artisan p:upgrade:safe --no-interaction)..."
if run_artisan p:upgrade:safe --no-interaction; then
    echo "Upgrade completed successfully."
    exit 0
fi

echo "Upgrade failed. Inspect storage/logs/panel-upgrade-*.log inside the container." >&2
exit 1
