#!/usr/bin/env bash
set -Eeuo pipefail

REPO="yupthatpandadk/Nodexa"
BRANCH="main"
RAW="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/pterodactyl}"

green='\033[0;32m'; red='\033[0;31m'; yellow='\033[1;33m'; cyan='\033[0;36m'; nc='\033[0m'
info(){ echo -e "${green}[Nodexa]${nc} $*"; }
warn(){ echo -e "${yellow}[Nodexa]${nc} $*"; }
fail(){ echo -e "${red}[Nodexa]${nc} $*" >&2; exit 1; }
pause(){ echo; read -r -p "Tryk Enter for at fortsætte..." _; }

[[ ${EUID} -eq 0 ]] || fail "Kør installeren som root."

run_remote() {
  local script="$1"
  local url="$RAW/installers/$script"
  info "Henter $script..."
  local tmp
  tmp="$(mktemp)"
  if ! curl -fsSL "$url" -o "$tmp"; then
    rm -f "$tmp"
    fail "Kunne ikke hente $url. Installer-modulet er muligvis ikke lagt på GitHub endnu."
  fi
  bash "$tmp"
  rm -f "$tmp"
}

update_panel() {
  [[ -d "$PANEL_DIR" ]] || fail "Panel-mappen findes ikke: $PANEL_DIR"
  cd "$PANEL_DIR"
  command -v git >/dev/null || fail "git er ikke installeret."
  [[ -d .git ]] || fail "$PANEL_DIR er ikke et Git repository."

  info "Opretter lokal backup..."
  mkdir -p storage/nodexa-backups
  tar --exclude='./storage/nodexa-backups' --exclude='./.git' -czf "storage/nodexa-backups/update-$(date +%Y%m%d-%H%M%S).tar.gz" . || true
  php artisan down 2>/dev/null || true

  if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "https://github.com/$REPO.git"
  else
    git remote add origin "https://github.com/$REPO.git"
  fi

  info "Henter Nodexa main..."
  git fetch origin "$BRANCH"
  git reset --hard "origin/$BRANCH"

  if command -v composer >/dev/null && [[ -f composer.json ]]; then
    composer install --no-dev --optimize-autoloader --no-interaction
  fi

  # Frontend source changes are not visible until the production bundle is rebuilt.
  if [[ -f package.json ]]; then
    info "Bygger Nodexa frontend..."
    if command -v corepack >/dev/null 2>&1; then
      corepack enable >/dev/null 2>&1 || true
    fi
    if command -v yarn >/dev/null 2>&1; then
      yarn install --frozen-lockfile || yarn install
      if ! yarn build:production; then
        if ! yarn production; then
          yarn build
        fi
      fi
    elif command -v npm >/dev/null 2>&1; then
      if [[ -f package-lock.json ]]; then npm ci; else npm install; fi
      if ! npm run build:production; then
        if ! npm run production; then
          npm run build
        fi
      fi
    else
      fail "Node/Yarn/NPM mangler. Frontend kan derfor ikke bygges."
    fi
  fi

  php artisan migrate --force
  php artisan optimize:clear || true
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  php artisan up 2>/dev/null || true
  systemctl restart pteroq 2>/dev/null || true
  systemctl restart nginx 2>/dev/null || true
  info "Nodexa Panel er opdateret."
}

banner() {
  clear
  echo -e "${cyan}"
  echo "  _   _           _                 "
  echo " | \ | | ___   __| | _____  ____ _ "
  echo " |  \| |/ _ \ / _\` |/ _ \ \/ / _\` |"
  echo " | |\  | (_) | (_| |  __/>  < (_| |"
  echo " |_| \_|\___/ \__,_|\___/_/\_\__,_|"
  echo -e "${nc}"
  echo "        Nodexa Installer"
  echo "======================================"
}

menu() {
  while true; do
    banner
    echo "  1) Install Nodexa Panel"
    echo "  2) Install Wings"
    echo "  3) Install PHPMyAdmin"
    echo "  4) Update Nodexa Panel"
    echo "  5) Update Wings"
    echo "  6) Repair Nodexa Panel"
    echo "  7) Switch Domain"
    echo "  8) Remove PHPMyAdmin"
    echo "  9) Remove Wings"
    echo " 10) Remove Nodexa Panel"
    echo "  0) Exit"
    echo
    read -r -p "Vælg [0-10]: " choice
    echo
    case "$choice" in
      1) run_remote panel.sh; pause ;;
      2) run_remote wings.sh; pause ;;
      3) run_remote phpmyadmin.sh; pause ;;
      4) update_panel; pause ;;
      5) run_remote updatewings.sh; pause ;;
      6) run_remote repair.sh; pause ;;
      7) run_remote switch_domains.sh; pause ;;
      8) run_remote remove_phpmyadmin.sh; pause ;;
      9) run_remote remove_wings.sh; pause ;;
      10) run_remote remove_panel.sh; pause ;;
      0) exit 0 ;;
      *) warn "Ugyldigt valg."; sleep 1 ;;
    esac
  done
}

case "${1:-}" in
  update|--update) update_panel ;;
  panel|install|--install) run_remote panel.sh ;;
  wings) run_remote wings.sh ;;
  phpmyadmin) run_remote phpmyadmin.sh ;;
  update-wings) run_remote updatewings.sh ;;
  repair) run_remote repair.sh ;;
  switch-domain) run_remote switch_domains.sh ;;
  remove-panel) run_remote remove_panel.sh ;;
  remove-wings) run_remote remove_wings.sh ;;
  remove-phpmyadmin) run_remote remove_phpmyadmin.sh ;;
  "") menu ;;
  *) fail "Ukendt kommando: $1" ;;
esac
