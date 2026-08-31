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

ask_yes_no(){
 local prompt="$1" default_value="${2:-y}" answer
 read -rp "$prompt" answer || true
 answer="${answer:-$default_value}"
 case "$answer" in y|Y|yes|YES|Yes) return 0;; *) return 1;; esac
}

ask_secret(){
 local prompt="$1" var_name="$2" value
 read -srp "$prompt" value || true
 echo
 printf -v "$var_name" '%s' "$value"
}

setup_wizard(){
 [[ "${NODEXA_SETUP_DONE:-0}" == "1" ]] && return 0

 clear 2>/dev/null || true
 echo "============================================================"
 echo "                    Nodexa Setup"
 echo "============================================================"
 echo "This setup configures the same core information you would"
 echo "normally configure separately in a game-panel installation:"
 echo "application, database, Redis, mail, administrator and HTTPS."
 echo

 echo "[1/6] Application Environment"
 echo "------------------------------------------------------------"
 if [[ -z "${NODEXA_DOMAIN:-}" ]]; then
  read -rp "Panel FQDN (example panel.example.com, blank for IP only): " NODEXA_DOMAIN || true
 fi
 NODEXA_DOMAIN="$(normalize_domain "${NODEXA_DOMAIN:-}")"
 NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}"
 if [[ "$NODEXA_DOMAIN" != "_" ]] && ! valid_domain "$NODEXA_DOMAIN"; then
  fail "Invalid panel domain: $NODEXA_DOMAIN"
 fi

 if [[ -z "${NODEXA_TIMEZONE:-}" ]]; then
  read -rp "Application timezone [Europe/Copenhagen]: " NODEXA_TIMEZONE || true
 fi
 NODEXA_TIMEZONE="${NODEXA_TIMEZONE:-Europe/Copenhagen}"
 if [[ ! -e "/usr/share/zoneinfo/$NODEXA_TIMEZONE" ]]; then
  fail "Unknown timezone: $NODEXA_TIMEZONE"
 fi

 if [[ -z "${NODEXA_APP_LOCALE:-}" ]]; then
  read -rp "Default language/locale [da]: " NODEXA_APP_LOCALE || true
 fi
 NODEXA_APP_LOCALE="${NODEXA_APP_LOCALE:-da}"

 echo
 echo "[2/6] Database"
 echo "------------------------------------------------------------"
 if [[ -z "${NODEXA_DB_HOST:-}" ]]; then read -rp "Database host [127.0.0.1]: " NODEXA_DB_HOST || true; fi
 NODEXA_DB_HOST="${NODEXA_DB_HOST:-127.0.0.1}"
 if [[ -z "${NODEXA_DB_PORT:-}" ]]; then read -rp "Database port [3306]: " NODEXA_DB_PORT || true; fi
 NODEXA_DB_PORT="${NODEXA_DB_PORT:-3306}"
 [[ "$NODEXA_DB_PORT" =~ ^[0-9]+$ ]] || fail "Database port must be numeric."
 if [[ -z "${NODEXA_DB_NAME:-}" ]]; then read -rp "Database name [nodexa]: " NODEXA_DB_NAME || true; fi
 NODEXA_DB_NAME="${NODEXA_DB_NAME:-nodexa}"
 if [[ -z "${NODEXA_DB_USER:-}" ]]; then read -rp "Database username [nodexa]: " NODEXA_DB_USER || true; fi
 NODEXA_DB_USER="${NODEXA_DB_USER:-nodexa}"

 if [[ -z "${NODEXA_DB_PASS+x}" ]]; then
  if [[ "$NODEXA_DB_HOST" == "127.0.0.1" || "$NODEXA_DB_HOST" == "localhost" ]]; then
   ask_secret "Database password (blank = generate secure password): " NODEXA_DB_PASS
   if [[ -z "$NODEXA_DB_PASS" ]]; then NODEXA_DB_PASS="$(openssl rand -hex 24)"; NODEXA_DB_PASSWORD_GENERATED=1; fi
  else
   while [[ -z "${NODEXA_DB_PASS:-}" ]]; do ask_secret "Database password: " NODEXA_DB_PASS; done
  fi
 fi
 NODEXA_DB_PASSWORD_GENERATED="${NODEXA_DB_PASSWORD_GENERATED:-0}"

 echo
 echo "[3/6] Cache, Session, Queue & Redis"
 echo "------------------------------------------------------------"
 NODEXA_CACHE_STORE="${NODEXA_CACHE_STORE:-redis}"
 NODEXA_SESSION_DRIVER="${NODEXA_SESSION_DRIVER:-redis}"
 NODEXA_QUEUE_CONNECTION="${NODEXA_QUEUE_CONNECTION:-redis}"
 if [[ -z "${NODEXA_REDIS_HOST:-}" ]]; then read -rp "Redis host [127.0.0.1]: " NODEXA_REDIS_HOST || true; fi
 NODEXA_REDIS_HOST="${NODEXA_REDIS_HOST:-127.0.0.1}"
 if [[ -z "${NODEXA_REDIS_PORT:-}" ]]; then read -rp "Redis port [6379]: " NODEXA_REDIS_PORT || true; fi
 NODEXA_REDIS_PORT="${NODEXA_REDIS_PORT:-6379}"
 [[ "$NODEXA_REDIS_PORT" =~ ^[0-9]+$ ]] || fail "Redis port must be numeric."
 if [[ -z "${NODEXA_REDIS_PASSWORD+x}" ]]; then ask_secret "Redis password [none]: " NODEXA_REDIS_PASSWORD; fi

 echo
 echo "[4/6] Mail"
 echo "------------------------------------------------------------"
 if [[ -z "${NODEXA_MAIL_MAILER:-}" ]]; then
  read -rp "Mail driver (log/smtp) [log]: " NODEXA_MAIL_MAILER || true
 fi
 NODEXA_MAIL_MAILER="${NODEXA_MAIL_MAILER:-log}"
 case "$NODEXA_MAIL_MAILER" in
  log) ;;
  smtp)
   if [[ -z "${NODEXA_MAIL_HOST:-}" ]]; then read -rp "SMTP host: " NODEXA_MAIL_HOST; fi
   [[ -n "$NODEXA_MAIL_HOST" ]] || fail "SMTP host is required."
   if [[ -z "${NODEXA_MAIL_PORT:-}" ]]; then read -rp "SMTP port [587]: " NODEXA_MAIL_PORT || true; fi
   NODEXA_MAIL_PORT="${NODEXA_MAIL_PORT:-587}"
   if [[ -z "${NODEXA_MAIL_USERNAME+x}" ]]; then read -rp "SMTP username [none]: " NODEXA_MAIL_USERNAME || true; fi
   if [[ -z "${NODEXA_MAIL_PASSWORD+x}" ]]; then ask_secret "SMTP password [none]: " NODEXA_MAIL_PASSWORD; fi
   if [[ -z "${NODEXA_MAIL_ENCRYPTION+x}" ]]; then read -rp "SMTP encryption (tls/ssl/none) [tls]: " NODEXA_MAIL_ENCRYPTION || true; fi
   NODEXA_MAIL_ENCRYPTION="${NODEXA_MAIL_ENCRYPTION:-tls}"
   [[ "$NODEXA_MAIL_ENCRYPTION" == "none" ]] && NODEXA_MAIL_ENCRYPTION=""
   ;;
  *) fail "Mail driver must be log or smtp.";;
 esac

 echo
 echo "[5/6] Administrator Account"
 echo "------------------------------------------------------------"
 echo "Create the first Nodexa administrator account."
 if [[ -z "${NODEXA_ADMIN_FIRST_NAME:-}" ]]; then
  while [[ -z "${NODEXA_ADMIN_FIRST_NAME:-}" ]]; do read -rp "First name: " NODEXA_ADMIN_FIRST_NAME || true; done
 fi
 if [[ -z "${NODEXA_ADMIN_LAST_NAME:-}" ]]; then
  while [[ -z "${NODEXA_ADMIN_LAST_NAME:-}" ]]; do read -rp "Last name: " NODEXA_ADMIN_LAST_NAME || true; done
 fi
 if [[ -z "${NODEXA_ADMIN_USERNAME:-}" ]]; then
  while true; do
   read -rp "Username: " NODEXA_ADMIN_USERNAME || true
   [[ "$NODEXA_ADMIN_USERNAME" =~ ^[A-Za-z0-9._-]{3,64}$ ]] && break
   echo "Use 3-64 letters, numbers, dot, underscore or dash."
  done
 fi
 if [[ -z "${NODEXA_ADMIN_EMAIL:-}" ]]; then
  while true; do
   read -rp "Email address: " NODEXA_ADMIN_EMAIL || true
   [[ "$NODEXA_ADMIN_EMAIL" == *@*.* ]] && break
   echo "Please enter a valid email address."
  done
 fi

 if [[ -z "${NODEXA_ADMIN_PASSWORD:-}" ]]; then
  while true; do
   ask_secret "Password (minimum 12 characters): " NODEXA_ADMIN_PASSWORD
   ask_secret "Confirm password: " NODEXA_ADMIN_PASSWORD_CONFIRM
   if [[ ${#NODEXA_ADMIN_PASSWORD} -lt 12 ]]; then
    echo "Password must be at least 12 characters."
    NODEXA_ADMIN_PASSWORD=""
    continue
   fi
   if [[ "$NODEXA_ADMIN_PASSWORD" != "$NODEXA_ADMIN_PASSWORD_CONFIRM" ]]; then
    echo "Passwords do not match. Try again."
    NODEXA_ADMIN_PASSWORD=""
    continue
   fi
   break
  done
 fi

 if [[ -z "${NODEXA_MAIL_FROM_ADDRESS:-}" ]]; then
  read -rp "Mail from address [${NODEXA_ADMIN_EMAIL}]: " NODEXA_MAIL_FROM_ADDRESS || true
 fi
 NODEXA_MAIL_FROM_ADDRESS="${NODEXA_MAIL_FROM_ADDRESS:-$NODEXA_ADMIN_EMAIL}"
 if [[ -z "${NODEXA_MAIL_FROM_NAME:-}" ]]; then read -rp "Mail from name [Nodexa]: " NODEXA_MAIL_FROM_NAME || true; fi
 NODEXA_MAIL_FROM_NAME="${NODEXA_MAIL_FROM_NAME:-Nodexa}"

 echo
 echo "[6/6] HTTPS / Let's Encrypt"
 echo "------------------------------------------------------------"
 if [[ "$NODEXA_DOMAIN" == "_" ]]; then
  NODEXA_ENABLE_SSL=0
  echo "No FQDN selected; Let's Encrypt will be skipped."
 else
  if [[ -z "${NODEXA_ENABLE_SSL:-}" ]]; then
   if ask_yes_no "Enable free HTTPS with Let's Encrypt? [Y/n]: " y; then NODEXA_ENABLE_SSL=1; else NODEXA_ENABLE_SSL=0; fi
  fi
 fi

 if [[ "${NODEXA_ENABLE_SSL:-0}" == "1" ]]; then
  if [[ -z "${NODEXA_SSL_EMAIL:-}" ]]; then read -rp "Let's Encrypt email [${NODEXA_ADMIN_EMAIL}]: " NODEXA_SSL_EMAIL || true; fi
  NODEXA_SSL_EMAIL="${NODEXA_SSL_EMAIL:-$NODEXA_ADMIN_EMAIL}"
  [[ "$NODEXA_SSL_EMAIL" == *@*.* ]] || fail "Invalid Let's Encrypt email."
 fi

 echo
 echo "============================================================"
 echo "Configuration summary"
 echo "============================================================"
 echo "Panel FQDN:       $([[ "$NODEXA_DOMAIN" == "_" ]] && echo 'IP only' || echo "$NODEXA_DOMAIN")"
 echo "Timezone:         ${NODEXA_TIMEZONE}"
 echo "Locale:           ${NODEXA_APP_LOCALE}"
 echo "Database:         ${NODEXA_DB_USER}@${NODEXA_DB_HOST}:${NODEXA_DB_PORT}/${NODEXA_DB_NAME}"
 echo "Database password: ************"
 echo "Redis:            ${NODEXA_REDIS_HOST}:${NODEXA_REDIS_PORT}"
 echo "Mail:             ${NODEXA_MAIL_MAILER}"
 echo "Administrator:    ${NODEXA_ADMIN_FIRST_NAME} ${NODEXA_ADMIN_LAST_NAME} (${NODEXA_ADMIN_USERNAME})"
 echo "Admin email:      ${NODEXA_ADMIN_EMAIL}"
 echo "Admin password:   ************"
 echo "HTTPS:            $([[ "${NODEXA_ENABLE_SSL:-0}" == "1" ]] && echo "Let's Encrypt" || echo 'Disabled')"
 echo "Updates:          Admin → Opdateringer"
 echo

 if [[ "${NODEXA_SKIP_CONFIRM:-0}" != "1" && -t 0 ]]; then
  if ! ask_yes_no "Save these settings and start installation? [Y/n]: " y; then
   echo "Installation cancelled."
   exit 0
  fi
 fi

 export NODEXA_DOMAIN NODEXA_TIMEZONE NODEXA_APP_LOCALE
 export NODEXA_DB_HOST NODEXA_DB_PORT NODEXA_DB_NAME NODEXA_DB_USER NODEXA_DB_PASS NODEXA_DB_PASSWORD_GENERATED
 export NODEXA_CACHE_STORE NODEXA_SESSION_DRIVER NODEXA_QUEUE_CONNECTION NODEXA_REDIS_HOST NODEXA_REDIS_PORT NODEXA_REDIS_PASSWORD
 export NODEXA_MAIL_MAILER NODEXA_MAIL_HOST NODEXA_MAIL_PORT NODEXA_MAIL_USERNAME NODEXA_MAIL_PASSWORD NODEXA_MAIL_ENCRYPTION NODEXA_MAIL_FROM_ADDRESS NODEXA_MAIL_FROM_NAME
 export NODEXA_ADMIN_FIRST_NAME NODEXA_ADMIN_LAST_NAME NODEXA_ADMIN_USERNAME NODEXA_ADMIN_EMAIL NODEXA_ADMIN_PASSWORD
 export NODEXA_ENABLE_SSL NODEXA_SSL_EMAIL
 export NODEXA_SETUP_DONE=1
}

# Backwards-compatible helper used by installer scripts.
ask_domain(){ setup_wizard; }
