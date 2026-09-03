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

# Installer bootstrap is commonly started with `curl ... | bash`, which means
# normal stdin is the curl pipe rather than the SSH terminal. Always prefer the
# controlling terminal for interactive questions. Most importantly, never hide
# EOF with `|| true`, since that previously caused validation loops to spam
# forever when stdin was unavailable.
prompt_read(){
 local prompt="$1" var_name="$2" value=""
 if [[ -r /dev/tty ]]; then
  IFS= read -r -p "$prompt" value </dev/tty || fail "Could not read input from /dev/tty. Reconnect your SSH session and try again."
 elif [[ -t 0 ]]; then
  IFS= read -r -p "$prompt" value || fail "Could not read terminal input."
 else
  fail "Interactive input is unavailable. Run the installer from an SSH terminal instead of a non-interactive pipe."
 fi
 printf -v "$var_name" '%s' "$value"
}

prompt_secret(){
 local prompt="$1" var_name="$2" value=""
 if [[ -r /dev/tty ]]; then
  IFS= read -r -s -p "$prompt" value </dev/tty || fail "Could not read secure input from /dev/tty. Reconnect your SSH session and try again."
  printf '\n' >/dev/tty
 elif [[ -t 0 ]]; then
  IFS= read -r -s -p "$prompt" value || fail "Could not read secure terminal input."
  echo
 else
  fail "Interactive input is unavailable. Run the installer from an SSH terminal."
 fi
 printf -v "$var_name" '%s' "$value"
}

ask_yes_no(){
 local prompt="$1" default_value="${2:-y}" answer=""
 prompt_read "$prompt" answer
 answer="${answer:-$default_value}"
 case "$answer" in y|Y|yes|YES|Yes) return 0;; *) return 1;; esac
}

ask_secret(){
 prompt_secret "$1" "$2"
}

setup_wizard(){
 [[ "${NODEXA_SETUP_DONE:-0}" == "1" ]] && return 0

 clear 2>/dev/null || true
 echo "################################################################"
 echo "#                     Nodexa Panel Setup                       #"
 echo "################################################################"
 echo ""
 echo "This setup configures the panel database, timezone, contact"
 echo "information, first administrator, FQDN, firewall and HTTPS."
 echo ""

 echo "################################################################"
 echo "# Database configuration"
 echo "################################################################"
 echo "These credentials are used by Nodexa to communicate with MySQL."
 echo "For a local database, Nodexa creates the database and user for you."
 echo ""

 NODEXA_DB_HOST="${NODEXA_DB_HOST:-127.0.0.1}"
 NODEXA_DB_PORT="${NODEXA_DB_PORT:-3306}"
 if [[ -z "${NODEXA_DB_NAME:-}" ]]; then
  prompt_read "Database name [nodexa]: " NODEXA_DB_NAME
 fi
 NODEXA_DB_NAME="${NODEXA_DB_NAME:-nodexa}"
 [[ "$NODEXA_DB_NAME" =~ ^[A-Za-z0-9_-]{1,64}$ ]] || fail "Database name may only contain letters, numbers, underscore and dash."

 if [[ -z "${NODEXA_DB_USER:-}" ]]; then
  prompt_read "Database username [nodexa]: " NODEXA_DB_USER
 fi
 NODEXA_DB_USER="${NODEXA_DB_USER:-nodexa}"
 [[ "$NODEXA_DB_USER" =~ ^[A-Za-z0-9_-]{1,32}$ ]] || fail "Database username may only contain letters, numbers, underscore and dash."

 if [[ -z "${NODEXA_DB_PASS+x}" ]]; then
  ask_secret "Database password (press Enter to generate a secure password): " NODEXA_DB_PASS
 fi
 if [[ -z "${NODEXA_DB_PASS:-}" ]]; then
  NODEXA_DB_PASS="$(openssl rand -hex 24)"
  NODEXA_DB_PASSWORD_GENERATED=1
 else
  NODEXA_DB_PASSWORD_GENERATED=0
 fi

 echo ""
 echo "################################################################"
 echo "# Panel information"
 echo "################################################################"

 if [[ -z "${NODEXA_TIMEZONE:-}" ]]; then
  prompt_read "Select timezone [Europe/Copenhagen]: " NODEXA_TIMEZONE
 fi
 NODEXA_TIMEZONE="${NODEXA_TIMEZONE:-Europe/Copenhagen}"
 [[ -e "/usr/share/zoneinfo/$NODEXA_TIMEZONE" ]] || fail "Unknown timezone: $NODEXA_TIMEZONE"
 NODEXA_APP_LOCALE="${NODEXA_APP_LOCALE:-da}"

 if [[ -z "${NODEXA_CONTACT_EMAIL:-}" ]]; then
  while true; do
   prompt_read "Email used for Nodexa and Let's Encrypt: " NODEXA_CONTACT_EMAIL
   [[ "$NODEXA_CONTACT_EMAIL" == *@*.* ]] && break
   echo "Please enter a valid email address."
  done
 fi
 [[ "$NODEXA_CONTACT_EMAIL" == *@*.* ]] || fail "Invalid contact email."

 echo ""
 echo "################################################################"
 echo "# Initial administrator account"
 echo "################################################################"
 echo "You choose the account information yourself. Nodexa does not"
 echo "generate or store your administrator password in plaintext."
 echo ""

 if [[ -z "${NODEXA_ADMIN_EMAIL:-}" ]]; then
  prompt_read "Email address for the initial admin account [${NODEXA_CONTACT_EMAIL}]: " NODEXA_ADMIN_EMAIL
 fi
 NODEXA_ADMIN_EMAIL="${NODEXA_ADMIN_EMAIL:-$NODEXA_CONTACT_EMAIL}"
 [[ "$NODEXA_ADMIN_EMAIL" == *@*.* ]] || fail "Invalid administrator email."

 if [[ -z "${NODEXA_ADMIN_USERNAME:-}" ]]; then
  while true; do
   prompt_read "Username for the initial admin account: " NODEXA_ADMIN_USERNAME
   [[ "$NODEXA_ADMIN_USERNAME" =~ ^[A-Za-z0-9._-]{3,64}$ ]] && break
   echo "Use 3-64 letters, numbers, dot, underscore or dash."
  done
 fi

 if [[ -z "${NODEXA_ADMIN_FIRST_NAME:-}" ]]; then
  while [[ -z "${NODEXA_ADMIN_FIRST_NAME:-}" ]]; do
   prompt_read "First name for the initial admin account: " NODEXA_ADMIN_FIRST_NAME
  done
 fi
 if [[ -z "${NODEXA_ADMIN_LAST_NAME:-}" ]]; then
  while [[ -z "${NODEXA_ADMIN_LAST_NAME:-}" ]]; do
   prompt_read "Last name for the initial admin account: " NODEXA_ADMIN_LAST_NAME
  done
 fi

 if [[ -z "${NODEXA_ADMIN_PASSWORD:-}" ]]; then
  while true; do
   ask_secret "Password for the initial admin account (minimum 12 characters): " NODEXA_ADMIN_PASSWORD
   ask_secret "Confirm admin password: " NODEXA_ADMIN_PASSWORD_CONFIRM
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

 echo ""
 echo "################################################################"
 echo "# Web configuration"
 echo "################################################################"

 if [[ -z "${NODEXA_DOMAIN:-}" ]]; then
  prompt_read "Set the FQDN of this panel (panel.example.com, blank for IP): " NODEXA_DOMAIN
 fi
 NODEXA_DOMAIN="$(normalize_domain "${NODEXA_DOMAIN:-}")"
 NODEXA_DOMAIN="${NODEXA_DOMAIN:-_}"
 if [[ "$NODEXA_DOMAIN" != "_" ]] && ! valid_domain "$NODEXA_DOMAIN"; then
  fail "Invalid panel FQDN: $NODEXA_DOMAIN"
 fi

 if [[ -z "${NODEXA_CONFIGURE_UFW:-}" ]]; then
  if ask_yes_no "Automatically configure UFW firewall? (y/N): " n; then
   NODEXA_CONFIGURE_UFW=1
  else
   NODEXA_CONFIGURE_UFW=0
  fi
 fi

 if [[ "$NODEXA_DOMAIN" == "_" ]]; then
  NODEXA_ENABLE_SSL=0
  echo "HTTPS via Let's Encrypt is unavailable without an FQDN."
 else
  if [[ -z "${NODEXA_ENABLE_SSL:-}" ]]; then
   if ask_yes_no "Automatically configure HTTPS using Let's Encrypt? (y/N): " n; then
    NODEXA_ENABLE_SSL=1
   else
    NODEXA_ENABLE_SSL=0
   fi
  fi
 fi
 NODEXA_SSL_EMAIL="${NODEXA_SSL_EMAIL:-$NODEXA_CONTACT_EMAIL}"

 # Keep Redis local by default. Advanced mail/Redis settings can be changed in
 # Nodexa after installation or supplied as NODEXA_* environment variables.
 NODEXA_CACHE_STORE="${NODEXA_CACHE_STORE:-redis}"
 NODEXA_SESSION_DRIVER="${NODEXA_SESSION_DRIVER:-redis}"
 NODEXA_QUEUE_CONNECTION="${NODEXA_QUEUE_CONNECTION:-redis}"
 NODEXA_REDIS_HOST="${NODEXA_REDIS_HOST:-127.0.0.1}"
 NODEXA_REDIS_PORT="${NODEXA_REDIS_PORT:-6379}"
 NODEXA_REDIS_PASSWORD="${NODEXA_REDIS_PASSWORD:-}"
 NODEXA_MAIL_MAILER="${NODEXA_MAIL_MAILER:-log}"
 NODEXA_MAIL_FROM_ADDRESS="${NODEXA_MAIL_FROM_ADDRESS:-$NODEXA_CONTACT_EMAIL}"
 NODEXA_MAIL_FROM_NAME="${NODEXA_MAIL_FROM_NAME:-Nodexa}"

 echo ""
 echo "################################################################"
 echo "# Configuration summary"
 echo "################################################################"
 echo "Database name:     ${NODEXA_DB_NAME}"
 echo "Database username: ${NODEXA_DB_USER}"
 echo "Database password: ************"
 echo "Timezone:          ${NODEXA_TIMEZONE}"
 echo "Contact email:     ${NODEXA_CONTACT_EMAIL}"
 echo "Admin account:     ${NODEXA_ADMIN_USERNAME} (${NODEXA_ADMIN_FIRST_NAME} ${NODEXA_ADMIN_LAST_NAME})"
 echo "Admin email:       ${NODEXA_ADMIN_EMAIL}"
 echo "Admin password:    ************"
 echo "Panel FQDN:        $([[ "$NODEXA_DOMAIN" == "_" ]] && echo 'IP only' || echo "$NODEXA_DOMAIN")"
 echo "UFW firewall:      $([[ "$NODEXA_CONFIGURE_UFW" == "1" ]] && echo 'Configure automatically' || echo 'Leave unchanged')"
 echo "HTTPS:             $([[ "${NODEXA_ENABLE_SSL:-0}" == "1" ]] && echo "Let's Encrypt" || echo 'Disabled')"
 echo "Updates:           Admin → Opdateringer"
 echo ""

 if [[ "${NODEXA_SKIP_CONFIRM:-0}" != "1" && ( -r /dev/tty || -t 0 ) ]]; then
  if ! ask_yes_no "Start installation with these settings? [Y/n]: " y; then
   echo "Installation cancelled."
   exit 0
  fi
 fi

 export NODEXA_DOMAIN NODEXA_TIMEZONE NODEXA_APP_LOCALE NODEXA_CONTACT_EMAIL
 export NODEXA_DB_HOST NODEXA_DB_PORT NODEXA_DB_NAME NODEXA_DB_USER NODEXA_DB_PASS NODEXA_DB_PASSWORD_GENERATED
 export NODEXA_CACHE_STORE NODEXA_SESSION_DRIVER NODEXA_QUEUE_CONNECTION NODEXA_REDIS_HOST NODEXA_REDIS_PORT NODEXA_REDIS_PASSWORD
 export NODEXA_MAIL_MAILER NODEXA_MAIL_FROM_ADDRESS NODEXA_MAIL_FROM_NAME
 export NODEXA_ADMIN_FIRST_NAME NODEXA_ADMIN_LAST_NAME NODEXA_ADMIN_USERNAME NODEXA_ADMIN_EMAIL NODEXA_ADMIN_PASSWORD
 export NODEXA_CONFIGURE_UFW NODEXA_ENABLE_SSL NODEXA_SSL_EMAIL
 export NODEXA_SETUP_DONE=1
}

ask_domain(){ setup_wizard; }
