#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="0.14.49"
REPO="${NODEXA_REPOSITORY:-yupthatpandadk/Nodexa}"
# Nodexa production installs must always deploy the complete main panel tree.
# NODEXA_BRANCH=pterodactyl-core was previously accepted and could replace a
# production Pterodactyl panel with an incompatible/partial codebase.
REQUESTED_BRANCH="${NODEXA_BRANCH:-main}"
if [[ "$REQUESTED_BRANCH" != "main" ]]; then
  echo "[Nodexa] Ignoring unsupported NODEXA_BRANCH=${REQUESTED_BRANCH}; using main."
fi
BRANCH="main"
URL="${NODEXA_SOURCE_URL:-https://github.com/${REPO}/archive/refs/heads/${BRANCH}.zip}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [[ $EUID -ne 0 ]]; then
  echo "[Nodexa] Run as root: sudo -i" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
APT_LOCK_FILES=(/var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock /var/cache/apt/archives/lock)
apt_lock_owners(){ command -v fuser >/dev/null 2>&1 || return 0; fuser "${APT_LOCK_FILES[@]}" 2>/dev/null | tr -cs '0-9' ' ' | xargs 2>/dev/null || true; }
wait_for_apt(){ local waited=0 max_wait="${NODEXA_APT_WAIT_SECONDS:-600}" owners; while true; do owners="$(apt_lock_owners)"; [[ -z "${owners//[[:space:]]/}" ]] && return 0; (( waited >= max_wait )) && { echo "[Nodexa] Timed out waiting for apt/dpkg: ${owners}" >&2; exit 1; }; sleep 5; waited=$((waited+5)); done; }
wait_for_network(){ local waited=0 max_wait="${NODEXA_NETWORK_WAIT_SECONDS:-300}"; until getent ahostsv4 github.com >/dev/null 2>&1 && getent ahostsv4 raw.githubusercontent.com >/dev/null 2>&1; do (( waited >= max_wait )) && { echo "[Nodexa] Timed out waiting for network." >&2; exit 1; }; sleep 5; waited=$((waited+5)); done; }
curl_retry(){ curl --fail --location --retry "${NODEXA_CURL_RETRIES:-8}" --retry-delay 3 --retry-all-errors --connect-timeout 15 "$@"; }
apt_install(){ wait_for_apt; apt-get -o DPkg::Lock::Timeout=600 update -y; wait_for_apt; apt-get -o DPkg::Lock::Timeout=600 install -y "$@"; }

echo "[Nodexa] Bootstrap version ${VERSION}"
echo "[Nodexa] Source branch: ${BRANCH}"
wait_for_apt
if command -v dpkg >/dev/null 2>&1 && ! dpkg --configure -a; then wait_for_apt; apt-get -o DPkg::Lock::Timeout=600 -f install -y; dpkg --configure -a; fi
command -v curl >/dev/null 2>&1 || apt_install curl ca-certificates
command -v unzip >/dev/null 2>&1 || apt_install unzip
wait_for_network
NODEXA_SOURCE_COMMIT="$(curl_retry -sS -H 'Accept: application/vnd.github+json' -H 'User-Agent: Nodexa-Installer' "https://api.github.com/repos/${REPO}/commits/${BRANCH}" 2>/dev/null | sed -n 's/.*"sha"[[:space:]]*:[[:space:]]*"\([0-9a-fA-F]\{40\}\)".*/\1/p' | head -n1 | tr 'A-F' 'a-f' || true)"
export NODEXA_SOURCE_COMMIT NODEXA_UPDATE_REPOSITORY="$REPO" NODEXA_UPDATE_BRANCH="$BRANCH"
echo "[Nodexa] Downloading installer ${VERSION}..."
[[ "$NODEXA_SOURCE_COMMIT" =~ ^[0-9a-f]{40}$ ]] && echo "[Nodexa] Source commit: ${NODEXA_SOURCE_COMMIT:0:8}"
curl_retry "$URL" -o "$TMP/nodexa.zip"
unzip -q "$TMP/nodexa.zip" -d "$TMP/src"
MENU="$(find "$TMP/src" -type f -path '*/installer/local-menu.sh' | head -n1)"
[[ -n "$MENU" ]] || { echo "[Nodexa] Invalid source archive." >&2; exit 1; }
if [[ "${NODEXA_NONINTERACTIVE:-0}" != "1" && -t 0 && -r /dev/tty ]]; then exec bash "$MENU" "$@" </dev/tty; fi
exec bash "$MENU" "$@"
