#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.14.12"
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

# Only treat apt/dpkg as busy when one of the real package-manager lock files
# is actively held. Process-name checks can be fooled by zombie/stale apt
# processes and caused fresh VPS installations to wait until timeout forever.
apt_is_busy() {
  if command -v fuser >/dev/null 2>&1; then
    fuser /var/lib/dpkg/lock-frontend \
          /var/lib/dpkg/lock \
          /var/lib/apt/lists/lock \
          /var/cache/apt/archives/lock >/dev/null 2>&1
    return $?
  fi

  # If fuser is unavailable, let apt itself handle locking. Do not guess from
  # process names because stale/zombie processes do not necessarily own locks.
  return 1
}

wait_for_apt() {
  local waited=0 max_wait="${NODEXA_APT_WAIT_SECONDS:-600}"
  while apt_is_busy; do
    if (( waited == 0 )); then
      echo "[Nodexa] Ubuntu package manager currently owns an apt/dpkg lock."
      echo "[Nodexa] Waiting for the real package-manager lock to be released..."
    fi
    if (( waited >= max_wait )); then
      echo "[Nodexa] Timed out after ${max_wait}s waiting for an apt/dpkg lock." >&2
      echo "[Nodexa] Check lock owners with:" >&2
      echo "[Nodexa] fuser -v /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock /var/cache/apt/archives/lock" >&2
      exit 1
    fi
    sleep 5
    waited=$((waited + 5))
  done
  if (( waited > 0 )); then
    echo "[Nodexa] Package-manager lock released after ${waited}s."
  fi
}

wait_for_network() {
  local waited=0 max_wait="${NODEXA_NETWORK_WAIT_SECONDS:-300}"
  while true; do
    if getent ahostsv4 github.com >/dev/null 2>&1 && getent ahostsv4 raw.githubusercontent.com >/dev/null 2>&1; then
      return 0
    fi
    if (( waited == 0 )); then
      echo "[Nodexa] DNS/network is not ready yet. Waiting for GitHub name resolution..."
    fi
    if (( waited >= max_wait )); then
      echo "[Nodexa] Timed out after ${max_wait}s waiting for DNS/network." >&2
      echo "[Nodexa] Check: resolvectl status" >&2
      echo "[Nodexa] Check: getent hosts raw.githubusercontent.com" >&2
      exit 1
    fi
    sleep 5
    waited=$((waited + 5))
  done
}

curl_retry() {
  local tries="${NODEXA_CURL_RETRIES:-8}"
  curl --fail --location --retry "$tries" --retry-delay 3 --retry-all-errors --connect-timeout 15 "$@"
}

apt_install() {
  wait_for_apt
  apt-get -o DPkg::Lock::Timeout=600 update -y
  wait_for_apt
  apt-get -o DPkg::Lock::Timeout=600 install -y "$@"
}

echo "[Nodexa] Checking package-manager state..."
wait_for_apt
if command -v dpkg >/dev/null 2>&1; then
  dpkg --configure -a || {
    echo "[Nodexa] dpkg configuration needs repair; attempting dependency recovery..."
    wait_for_apt
    apt-get -o DPkg::Lock::Timeout=600 -f install -y
    wait_for_apt
    dpkg --configure -a
  }
fi

command -v curl >/dev/null 2>&1 || apt_install curl ca-certificates
command -v unzip >/dev/null 2>&1 || apt_install unzip
command -v curl >/dev/null 2>&1 || { echo "[Nodexa] curl installation failed." >&2; exit 1; }
command -v unzip >/dev/null 2>&1 || { echo "[Nodexa] unzip installation failed." >&2; exit 1; }

wait_for_network

NODEXA_SOURCE_COMMIT="$(curl_retry -sS -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Installer' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | sed -n 's/.*"sha"[[:space:]]*:[[:space:]]*"\([0-9a-fA-F]\{40\}\)".*/\1/p' | head -n1 | tr 'A-F' 'a-f' || true)"
export NODEXA_SOURCE_COMMIT
export NODEXA_UPDATE_REPOSITORY="$REPO"
export NODEXA_UPDATE_BRANCH="$BRANCH"

echo "[Nodexa] Downloading installer ${VERSION}..."
if [[ "$NODEXA_SOURCE_COMMIT" =~ ^[0-9a-f]{40}$ ]]; then
  echo "[Nodexa] Source commit: ${NODEXA_SOURCE_COMMIT:0:8}"
else
  echo "[Nodexa] Source commit could not be resolved yet; updater will retry before Agent build."
fi

curl_retry "$URL" -o "$TMP/nodexa.zip"
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
MENU="$(find "$TMP/src" -type f -path '*/installer/local-menu.sh' | head -n1)"
[[ -n "$MENU" ]] || { echo "[Nodexa] Invalid source archive." >&2; exit 1; }

# `curl ... | bash` uses the curl pipe as stdin. Attach every interactive mode
# (menu, panel, agent and both) to the controlling SSH terminal when available.
if [[ -r /dev/tty ]]; then
  exec bash "$MENU" "$@" </dev/tty
fi

exec bash "$MENU" "$@"
