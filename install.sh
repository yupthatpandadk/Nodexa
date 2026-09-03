#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.14.3"
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

NODEXA_SOURCE_COMMIT="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Installer' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | grep -oE '\"sha\"[[:space:]]*:[[:space:]]*\"[0-9a-fA-F]{40}\"' | head -n1 | cut -d'\"' -f4 | tr 'A-F' 'a-f' || true)"
export NODEXA_SOURCE_COMMIT
export NODEXA_UPDATE_REPOSITORY="$REPO"
export NODEXA_UPDATE_BRANCH="$BRANCH"

echo "[Nodexa] Downloading installer ${VERSION}..."
if [[ "$NODEXA_SOURCE_COMMIT" =~ ^[0-9a-f]{40}$ ]]; then
  echo "[Nodexa] Source commit: ${NODEXA_SOURCE_COMMIT:0:8}"
else
  echo "[Nodexa] Source commit could not be resolved yet; updater will retry before Agent build."
fi
curl -fL "$URL" -o "$TMP/nodexa.zip"
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
MENU="$(find "$TMP/src" -type f -path '*/installer/local-menu.sh' | head -n1)"
[[ -n "$MENU" ]] || { echo "[Nodexa] Invalid source archive." >&2; exit 1; }

# When this bootstrap is executed using `curl ... | bash`, stdin belongs to the
# curl pipe. Reattach stdin to the user's terminal so the interactive menu can
# actually receive selections. Command modes such as `both` also continue to
# work in non-interactive environments.
if [[ $# -eq 0 && -r /dev/tty ]]; then
  exec bash "$MENU" </dev/tty
fi

exec bash "$MENU" "$@"
