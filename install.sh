#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.4.2"
REPO="${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_BRANCH:-main}"
URL="${NODEXA_SOURCE_URL:-https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
if [[ $EUID -ne 0 ]]; then echo "[Nodexa] Run as root: sudo -i" >&2; exit 1; fi
command -v curl >/dev/null 2>&1 || { apt-get update -y && apt-get install -y curl; }
command -v unzip >/dev/null 2>&1 || { apt-get update -y && apt-get install -y unzip; }
echo "[Nodexa] Downloading installer ${VERSION}..."
curl -fL "$URL" -o "$TMP/nodexa.zip"
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
MENU="$(find "$TMP/src" -type f -path '*/installer/local-menu.sh' | head -n1)"
[[ -n "$MENU" ]] || { echo "[Nodexa] Invalid source archive." >&2; exit 1; }
exec bash "$MENU"
