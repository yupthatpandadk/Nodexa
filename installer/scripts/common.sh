#!/usr/bin/env bash
set -Eeuo pipefail

log(){ printf '\n\033[1;36m[Nodexa]\033[0m %s\n' "$*"; }
fail(){ printf '\n\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }
warn(){ printf '\n\033[1;33m[WARN]\033[0m %s\n' "$*" >&2; }

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

normalize_domain(){
 local value="$1"
 value="${value#http://}"
 value="${value#https://}"
 value="${value%%/*}"
 value="${value%.}"
 printf '%s' "$value"
}

valid_domain(){
 [[ "$1" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]
}

setup_wizard(){
 [[ "${NODEXA_SETUP_DONE:-0}" == "1" ]] && return 0

 echo
 echo "============================================================"
 echo "                 Nodexa Setup Wizard"
 echo "============================================================"
 echo "This wizard configures the panel, administrator and HTTPS."
 echo

 if [[ -z "${NODEXA_DOMAIN:-}" ]]; then
  read -rp "Panel domain (example panel.example.com, blank for IP only): " NODEXA_DOMAIN || true
 fi
 NODEXA_DOMAIN="$(normalize_domain "${NODEXA_DOMAIN:-}")"
 NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}"
 if [[ "$NODEXA_DOMAIN" != "_" ]] && ! valid_domain "$NODEXA_DOMAIN"; then
  fail "Invalid panel domain: $NODEXA_DOMAIN"
 fi

 if [[ -z "${NODEXA_ADMIN_NAME:-}" ]]; then
  read -rp "Administrator name [Administrator]: " NODEXA_ADMIN_NAME || true
 fi
 NODEXA_ADMIN_NAME="${NODEXA_ADMIN_NAME:-Administrator}"

 if [[ -z "${NODEXA_ADMIN_EMAIL:-}" ]]; then
  while true; do
   read -rp "Administrator email: " NODEXA_ADMIN_EMAIL || true
   [[ "$NODEXA_ADMIN_EMAIL" == *@*.* ]] && break
   echo "Please enter a valid email address."
  done
 fi
 [[ "$NODEXA_ADMIN_EMAIL" == *@*.* ]] || fail "Invalid administrator email."

 if [[ -z "${NODEXA_ADMIN_PASSWORD+x}" ]]; then
  read -srp "Administrator password (blank = generate a strong password): " NODEXA_ADMIN_PASSWORD || true
  echo
 fi
 if [[ -n "${NODEXA_ADMIN_PASSWORD:-}" && ${#NODEXA_ADMIN_PASSWORD} -lt 12 ]]; then
  fail "Administrator password must be at least 12 characters, or leave it blank to generate one."
 fi

 if [[ "$NODEXA_DOMAIN" == "_" ]]; then
  NODEXA_ENABLE_SSL=0
 else
  if [[ -z "${NODEXA_ENABLE_SSL:-}" ]]; then
   local answer
   read -rp "Enable free HTTPS with Let's Encrypt? [Y/n]: " answer || true
   case "${answer:-y}" in
    n|N|no|NO|No) NODEXA_ENABLE_SSL=0 ;;
    *) NODEXA_ENABLE_SSL=1 ;;
   esac
  fi
 fi

 if [[ "${NODEXA_ENABLE_SSL:-0}" == "1" ]]; then
  if [[ -z "${NODEXA_SSL_EMAIL:-}" ]]; then
   read -rp "Let's Encrypt email [${NODEXA_ADMIN_EMAIL}]: " NODEXA_SSL_EMAIL || true
  fi
  NODEXA_SSL_EMAIL="${NODEXA_SSL_EMAIL:-$NODEXA_ADMIN_EMAIL}"
  [[ "$NODEXA_SSL_EMAIL" == *@*.* ]] || fail "Invalid Let's Encrypt email."
 fi

 echo
 echo "Setup summary"
 echo "  Panel:        $([[ "$NODEXA_DOMAIN" == "_" ]] && echo 'Server IP (HTTP)' || echo "$NODEXA_DOMAIN")"
 echo "  Administrator: ${NODEXA_ADMIN_NAME} <${NODEXA_ADMIN_EMAIL}>"
 echo "  Admin password: $([[ -n "${NODEXA_ADMIN_PASSWORD:-}" ]] && echo 'Custom password' || echo 'Generate automatically')"
 echo "  HTTPS:        $([[ "${NODEXA_ENABLE_SSL:-0}" == "1" ]] && echo "Let's Encrypt" || echo 'Disabled')"
 echo "  Updates:      Enabled in Admin → Opdateringer"
 echo

 if [[ "${NODEXA_SKIP_CONFIRM:-0}" != "1" && -t 0 ]]; then
  local confirm
  read -rp "Start installation? [Y/n]: " confirm || true
  case "${confirm:-y}" in n|N|no|NO|No) echo "Installation cancelled."; exit 0;; esac
 fi

 export NODEXA_DOMAIN NODEXA_ADMIN_NAME NODEXA_ADMIN_EMAIL NODEXA_ADMIN_PASSWORD NODEXA_ENABLE_SSL NODEXA_SSL_EMAIL
 export NODEXA_SETUP_DONE=1
}

# Backwards-compatible helper used by older installer scripts.
ask_domain(){ setup_wizard; }
