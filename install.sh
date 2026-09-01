#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.9.5"
REPO="${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_BRANCH:-main}"
URL="${NODEXA_SOURCE_URL:-https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
if [[ $EUID -ne 0 ]]; then echo "[Nodexa] Run as root: sudo -i" >&2; exit 1; fi

# Recover automatically when a previous apt/dpkg operation was interrupted.
# This makes Nodexa safe to rerun after a disconnected SSH/mobile session.
if command -v dpkg >/dev/null 2>&1; then
  echo "[Nodexa] Checking package-manager state..."
  export DEBIAN_FRONTEND=noninteractive
  dpkg --configure -a || {
    echo "[Nodexa] dpkg configuration needs repair; attempting dependency recovery..."
    apt-get -f install -y
    dpkg --configure -a
  }
  apt-get -f install -y >/dev/null 2>&1 || true
fi

command -v curl >/dev/null 2>&1 || { apt-get update -y && apt-get install -y curl; }
command -v unzip >/dev/null 2>&1 || { apt-get update -y && apt-get install -y unzip; }

NODEXA_SOURCE_COMMIT="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Installer' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | sed -n 's/^[[:space:]]*"sha": "\([0-9a-f]\{40\}\)",/\1/p' | head -n1 || true)"
export NODEXA_SOURCE_COMMIT
export NODEXA_UPDATE_REPOSITORY="$REPO"
export NODEXA_UPDATE_BRANCH="$BRANCH"

echo "[Nodexa] Downloading installer ${VERSION}..."
[[ -n "$NODEXA_SOURCE_COMMIT" ]] && echo "[Nodexa] Source commit: ${NODEXA_SOURCE_COMMIT:0:8}"
curl -fL "$URL" -o "$TMP/nodexa.zip"
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
MENU="$(find "$TMP/src" -type f -path '*/installer/local-menu.sh' | head -n1)"
[[ -n "$MENU" ]] || { echo "[Nodexa] Invalid source archive." >&2; exit 1; }
exec bash "$MENU" "$@"
