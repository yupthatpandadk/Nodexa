#!/usr/bin/env bash
set -Eeuo pipefail
log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
fail(){ printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }
ensure_ubuntu(){
 [[ $EUID -eq 0 ]] || fail "Run as root."
 command -v apt-get >/dev/null 2>&1 || fail "Ubuntu/Debian with apt is required."
 . /etc/os-release || true
 case "${ID:-}" in ubuntu|debian) ;; *) fail "Unsupported OS: ${PRETTY_NAME:-unknown}" ;; esac
}
prepare_source(){
 ensure_ubuntu
 SOURCE_ROOT="${NODEXA_SOURCE_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
 [[ -f "$SOURCE_ROOT/deploy/install-runtime.sh" ]] || fail "Could not find Nodexa source tree."
 export SOURCE_ROOT
}
ask_domain(){
 if [[ -z "${NODEXA_DOMAIN:-}" ]]; then
  read -rp "Panel domain (example panel.example.com, blank for IP): " NODEXA_DOMAIN || true
  export NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}"
 fi
}
