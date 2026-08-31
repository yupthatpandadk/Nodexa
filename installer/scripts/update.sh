#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source
log "Updating Nodexa..."
if [[ -d /var/www/nodexa/panel ]]; then
  apt-get install -y rsync
  rsync -a --delete "$SOURCE_ROOT/panel/" /var/www/nodexa/panel/
  cd /var/www/nodexa/panel
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan migrate --force
  npm install && npm run build
  chown -R www-data:www-data storage bootstrap/cache
fi
if [[ -d /var/www/nodexa/agent ]]; then
  apt-get install -y rsync
  rsync -a --delete "$SOURCE_ROOT/agent/" /var/www/nodexa/agent/
  cd /var/www/nodexa/agent
  go mod tidy && go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad
  systemctl restart nodexa-agent
fi
systemctl restart nodexa-queue 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
log "Update complete."
