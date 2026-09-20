#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/pterodactyl}"
green='\033[0;32m'; red='\033[0;31m'; yellow='\033[1;33m'; cyan='\033[0;36m'; nc='\033[0m'
info(){ echo -e "${green}[REPAIR]${nc} $*"; }
warn(){ echo -e "${yellow}[REPAIR]${nc} $*"; }
fail(){ echo -e "${red}[REPAIR]${nc} $*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || fail "Kør Repair som root."
[[ -d "$PANEL_DIR" ]] || fail "Panel-mappen findes ikke: $PANEL_DIR"
cd "$PANEL_DIR"
[[ -f artisan && -f composer.json ]] || fail "$PANEL_DIR ligner ikke en Nodexa/Pterodactyl panel-installation."

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="/root/nodexa-repair-$STAMP"
mkdir -p "$BACKUP"
cp -a .env "$BACKUP/.env" 2>/dev/null || true
cp -a composer.json composer.lock package.json yarn.lock "$BACKUP/" 2>/dev/null || true
info "Backup gemt i $BACKUP"

php artisan down 2>/dev/null || true

# Detect PHP-FPM service.
PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
FPM="php${PHPV}-fpm"

info "Kontrollerer PHP og nødvendige extensions..."
command -v php >/dev/null || fail "PHP mangler."
command -v composer >/dev/null || fail "Composer mangler."
for ext in bcmath curl dom fileinfo gd intl mbstring openssl pdo tokenizer xml zip; do
  php -m | grep -qi "^$ext$" || warn "PHP extension mangler: $ext"
done

info "Reparerer Composer dependencies..."
export COMPOSER_ALLOW_SUPERUSER=1
rm -rf vendor
composer clear-cache >/dev/null 2>&1 || true
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

info "Rydder gamle Laravel caches..."
rm -f bootstrap/cache/*.php
rm -rf storage/framework/views/*
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

# Generate APP_KEY only on a genuinely new installation. Never rotate an existing key.
if [[ -f .env ]] && ! grep -Eq '^APP_KEY=base64:.+' .env; then
  warn "APP_KEY mangler. Genererer kun fordi ingen eksisterende APP_KEY blev fundet."
  php artisan key:generate --force
fi

info "Kører database migrations..."
php artisan migrate --force

info "Rydder og genopbygger Laravel caches..."
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend assets. Prefer already-built public assets; rebuild only when toolchain is available.
if [[ -f package.json ]]; then
  NEED_BUILD=0
  [[ -f public/mix-manifest.json || -f public/build/manifest.json ]] || NEED_BUILD=1
  if [[ $NEED_BUILD -eq 1 ]]; then
    info "Frontend manifest mangler; forsøger at bygge assets..."
    if command -v yarn >/dev/null; then
      yarn install --frozen-lockfile || yarn install
      yarn build || yarn run build || yarn production || yarn run production
    elif command -v npm >/dev/null; then
      if [[ -f package-lock.json ]]; then npm ci; else npm install; fi
      npm run build || npm run production
    else
      warn "Node/Yarn/NPM mangler, så frontend kan ikke genbygges automatisk."
    fi
  fi
fi

info "Retter ejerskab og permissions..."
chown -R www-data:www-data "$PANEL_DIR"
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;

info "Genstarter services..."
systemctl restart redis-server 2>/dev/null || systemctl restart redis 2>/dev/null || true
systemctl restart "$FPM" 2>/dev/null || true
systemctl restart pteroq 2>/dev/null || true
systemctl restart nginx 2>/dev/null || true

php artisan up 2>/dev/null || true

echo
echo -e "${cyan}========== NODEXA DIAGNOSTIC ==========${nc}"
FAIL=0
php artisan --version || FAIL=1
composer validate --no-check-publish >/dev/null 2>&1 || warn "composer.json har validation-advarsler."
php -r "require 'vendor/autoload.php'; echo 'Composer autoload: OK'.PHP_EOL;" || FAIL=1

for p in storage bootstrap/cache; do
  [[ -w "$p" ]] && echo "$p: writable" || { echo "$p: IKKE writable"; FAIL=1; }
done

if systemctl is-active --quiet nginx; then echo "nginx: running"; else echo "nginx: FEJL"; FAIL=1; fi
if systemctl list-unit-files "$FPM.service" >/dev/null 2>&1; then
  if systemctl is-active --quiet "$FPM"; then echo "$FPM: running"; else echo "$FPM: FEJL"; FAIL=1; fi
fi

LOG="storage/logs/laravel-$(date +%Y-%m-%d).log"
if [[ -f "$LOG" ]]; then
  echo
  echo "Seneste Laravel-fejl:"
  grep -E 'production\.(ERROR|CRITICAL)' "$LOG" | tail -n 3 || echo "Ingen ERROR/CRITICAL fundet i dagens log."
fi

echo
if [[ $FAIL -eq 0 ]]; then
  info "Repair er færdig. Grundlæggende panel-checks er OK."
else
  warn "Repair gennemførte, men diagnosticeringen fandt stadig fejl. Se outputtet ovenfor."
  exit 2
fi
