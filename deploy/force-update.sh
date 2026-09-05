#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] Run this as root (sudo -i)." >&2; exit 1; }

REPO="${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_BRANCH:-main}"
RAW="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

command -v curl >/dev/null 2>&1 || { apt-get update -y && apt-get install -y curl; }

echo "[Nodexa] Forcing update from ${REPO}@${BRANCH}..."

# Always run the updater from the latest repository source. This bypasses a stale
# local version marker without touching .env, storage or existing server data.
bash <(curl -fsSL "${RAW}/install.sh") update

echo "[Nodexa] Repairing updater registration..."
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
curl -fsSL "https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip" -o "$TMP/nodexa.zip"
apt-get install -y unzip >/dev/null 2>&1 || true
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
ROOT="$(find "$TMP/src" -maxdepth 1 -mindepth 1 -type d | head -n1)"
[[ -n "$ROOT" ]] || { echo "[Nodexa] Could not locate downloaded source tree." >&2; exit 1; }
bash "$ROOT/deploy/setup-updater.sh"

echo "[Nodexa] Force update completed."
