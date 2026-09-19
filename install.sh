#!/usr/bin/env bash
set -Eeuo pipefail

REPO="yupthatpandadk/Nodexa"
BRANCH="main"
RAW="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/pterodactyl}"

green='\033[0;32m'; red='\033[0;31m'; yellow='\033[1;33m'; nc='\033[0m'
info(){ echo -e "${green}[Nodexa]${nc} $*"; }
warn(){ echo -e "${yellow}[Nodexa]${nc} $*"; }
fail(){ echo -e "${red}[Nodexa]${nc} $*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || fail "Kør scriptet som root."

update_panel() {
  [[ -d "$PANEL_DIR" ]] || fail "Panel-mappen findes ikke: $PANEL_DIR"
  cd "$PANEL_DIR"

  command -v git >/dev/null || fail "git er ikke installeret."
  [[ -d .git ]] || fail "$PANEL_DIR er ikke et Git repository."

  info "Opretter lokal backup..."
  mkdir -p storage/nodexa-backups
  tar --exclude='./storage/nodexa-backups' --exclude='./.git' -czf "storage/nodexa-backups/update-$(date +%Y%m%d-%H%M%S).tar.gz" . || true

  info "Aktiverer maintenance mode..."
  php artisan down 2>/dev/null || true

  if ! git remote get-url origin >/dev/null 2>&1; then
    git remote add origin "https://github.com/$REPO.git"
  else
    git remote set-url origin "https://github.com/$REPO.git"
  fi

  info "Henter Nodexa main fra GitHub..."
  git fetch origin "$BRANCH"
  git reset --hard "origin/$BRANCH"

  if command -v composer >/dev/null && [[ -f composer.json ]]; then
    info "Installerer PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
  fi

  info "Kører database migrations..."
  php artisan migrate --force

  php artisan optimize:clear || true
  php artisan view:clear || true
  php artisan config:clear || true
  php artisan route:clear || true

  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  php artisan up 2>/dev/null || true
  systemctl restart pteroq 2>/dev/null || true
  systemctl restart nginx 2>/dev/null || true

  info "Nodexa er opdateret fra GitHub main."
}

install_panel() {
  fail "Nyinstallation er ikke aktiveret i denne bootstrap endnu. Brug update på en eksisterende Nodexa-installation."
}

case "${1:-}" in
  update|--update) update_panel ;;
  install|--install) install_panel ;;
  *)
    echo "Nodexa Installer / Updater"
    echo
    echo "Brug:"
    echo "  bash <(curl -fsSL $RAW/install.sh) update"
    echo
    echo "Panel mappe: $PANEL_DIR"
    ;;
esac
