#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.14.19"
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

APT_LOCK_FILES=(
  /var/lib/dpkg/lock-frontend
  /var/lib/dpkg/lock
  /var/lib/apt/lists/lock
  /var/cache/apt/archives/lock
)

apt_lock_owners() {
  command -v fuser >/dev/null 2>&1 || return 0
  # Do not use fuser's exit status as the lock decision. We only consider the
  # package manager busy when fuser actually returns one or more numeric PIDs.
  fuser "${APT_LOCK_FILES[@]}" 2>/dev/null | tr -cs '0-9' ' ' | xargs 2>/dev/null || true
}

apt_is_busy() {
  local owners
  owners="$(apt_lock_owners)"
  [[ -n "${owners//[[:space:]]/}" ]]
}

wait_for_apt() {
  local waited=0 max_wait="${NODEXA_APT_WAIT_SECONDS:-600}" owners
  while true; do
    owners="$(apt_lock_owners)"
    if [[ -z "${owners//[[:space:]]/}" ]]; then
      if (( waited > 0 )); then
        echo "[Nodexa] Package-manager lock released after ${waited}s."
      fi
      return 0
    fi

    if (( waited == 0 )); then
      echo "[Nodexa] Active apt/dpkg lock owner PID(s): ${owners}"
      echo "[Nodexa] Waiting for the real package-manager lock to be released..."
    fi
    if (( waited >= max_wait )); then
      echo "[Nodexa] Timed out after ${max_wait}s waiting for apt/dpkg PID(s): ${owners}" >&2
      echo "[Nodexa] Inspect with: ps -fp ${owners}" >&2
      exit 1
    fi
    sleep 5
    waited=$((waited + 5))
  done
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

echo "[Nodexa] Bootstrap version ${VERSION}"
echo "[Nodexa] Checking package-manager state..."
wait_for_apt
if command -v dpkg >/dev/null 2>&1; then
  if ! dpkg --configure -a; then
    echo "[Nodexa] dpkg configuration needs repair; attempting dependency recovery..."
    wait_for_apt
    apt-get -o DPkg::Lock::Timeout=600 -f install -y
    wait_for_apt
    dpkg --configure -a
  fi
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

# Interactive Termius/SSH installs should read answers directly from the real TTY.
# systemd/web updater runs have no controlling terminal, even though /dev/tty may
# exist as a device node. Never attempt to open it in non-interactive update mode.
if [[ "${NODEXA_NONINTERACTIVE:-0}" != "1" && -t 0 && -r /dev/tty ]]; then
  exec bash "$MENU" "$@" </dev/tty
fi

exec bash "$MENU" "$@"
