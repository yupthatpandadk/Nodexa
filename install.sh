#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.14.9"
REPO="${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_BRANCH:-main}"
URL="${NODEXA_SOURCE_URL:-https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [[ $EUID -ne 0 ]]; then
  echo "[Nodexa] Run as root: sudo -i" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt_is_busy() {
  pgrep -x apt >/dev/null 2>&1 || \
  pgrep -x apt-get >/dev/null 2>&1 || \
  pgrep -x dpkg >/dev/null 2>&1 || \
  pgrep -f 'unattended-upgrade' >/dev/null 2>&1 || \
  { command -v fuser >/dev/null 2>&1 && fuser /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock /var/cache/apt/archives/lock >/dev/null 2>&1; }
}

wait_for_apt() {
  local waited=0 max_wait="${NODEXA_APT_WAIT_SECONDS:-600}"
  while apt_is_busy; do
    if (( waited == 0 )); then
      echo "[Nodexa] Ubuntu package manager is busy (common just after a fresh VPS install)."
      echo "[Nodexa] Waiting for apt/cloud-init to finish; lock files will NOT be deleted..."
    fi
    if (( waited >= max_wait )); then
      echo "[Nodexa] Timed out after ${max_wait}s waiting for apt/dpkg." >&2
      echo "[Nodexa] Check: ps aux | grep -E 'apt|dpkg|unattended'" >&2
      exit 1
    fi
    sleep 5
    waited=$((waited + 5))
  done
  if (( waited > 0 )); then
    echo "[Nodexa] Package manager is available after ${waited}s."
  fi
}

apt_install() {
  wait_for_apt
  apt-get update -y
  wait_for_apt
  apt-get install -y "$@"
}

echo "[Nodexa] Checking package-manager state..."
wait_for_apt
if command -v dpkg >/dev/null 2>&1; then
  dpkg --configure -a || {
    echo "[Nodexa] dpkg configuration needs repair; attempting dependency recovery..."
    wait_for_apt
    apt-get -f install -y
    dpkg --configure -a
  }
fi

command -v curl >/dev/null 2>&1 || apt_install curl ca-certificates
command -v unzip >/dev/null 2>&1 || apt_install unzip
command -v curl >/dev/null 2>&1 || { echo "[Nodexa] curl installation failed." >&2; exit 1; }
command -v unzip >/dev/null 2>&1 || { echo "[Nodexa] unzip installation failed." >&2; exit 1; }

NODEXA_SOURCE_COMMIT="$(curl -fsSL -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Installer' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | sed -n 's/.*"sha"[[:space:]]*:[[:space:]]*"\([0-9a-fA-F]\{40\}\)".*/\1/p' | head -n1 | tr 'A-F' 'a-f' || true)"
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

# `curl ... | bash` uses the curl pipe as stdin. Attach every interactive mode
# (menu, panel, agent and both) to the controlling SSH terminal when available.
if [[ -r /dev/tty ]]; then
  exec bash "$MENU" "$@" </dev/tty
fi

exec bash "$MENU" "$@"
