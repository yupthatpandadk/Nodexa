#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
ACTION="${1:-}"

log(){ echo "[Nodexa Diagnostics] $*"; }
fail(){ echo "[Nodexa Diagnostics] ERROR: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "This helper must run as root."
[[ -d "$PANEL_DIR" ]] || fail "Panel directory not found: $PANEL_DIR"

run_artisan(){
    cd "$PANEL_DIR"
    sudo -u www-data /usr/bin/php artisan "$@"
}

case "$ACTION" in
    permissions)
        log "Repairing Laravel writable directories..."
        install -d -o www-data -g www-data \
            "$PANEL_DIR/storage/framework/cache/data" \
            "$PANEL_DIR/storage/framework/sessions" \
            "$PANEL_DIR/storage/framework/views" \
            "$PANEL_DIR/storage/logs" \
            "$PANEL_DIR/bootstrap/cache"
        chown -R www-data:www-data "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
        chmod -R 775 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
        ;;

    storage-link)
        log "Repairing public storage link..."
        rm -f "$PANEL_DIR/public/storage" 2>/dev/null || true
        run_artisan storage:link
        ;;

    clear-cache)
        log "Clearing Laravel caches..."
        run_artisan optimize:clear
        rm -f "$PANEL_DIR/storage/framework/views"/*.php 2>/dev/null || true
        ;;

    restart-queue)
        log "Restarting Nodexa queue worker..."
        systemctl reset-failed nodexa-queue.service 2>/dev/null || true
        systemctl restart nodexa-queue.service
        ;;

    restart-scheduler)
        log "Restarting Nodexa scheduler..."
        systemctl reset-failed nodexa-scheduler.timer 2>/dev/null || true
        systemctl restart nodexa-scheduler.timer
        ;;

    restart-web)
        log "Checking Nginx and restarting PHP-FPM..."
        nginx -t
        while read -r svc; do
            [[ -n "$svc" ]] && systemctl restart "$svc"
        done < <(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}')
        systemctl reload nginx
        ;;

    local-wings)
        [[ -s /etc/pterodactyl/config.yml ]] || fail "No local Wings configuration found."
        log "Attempting non-destructive local Wings recovery..."
        install -d /etc/nodexa
        ln -sfn /etc/pterodactyl/config.yml /etc/nodexa/config.yml
        systemctl daemon-reload
        systemctl reset-failed nodexa-agent.service wings.service 2>/dev/null || true
        if systemctl cat nodexa-agent.service >/dev/null 2>&1; then
            systemctl restart nodexa-agent.service
        elif systemctl cat wings.service >/dev/null 2>&1; then
            systemctl restart wings.service
        else
            fail "No Wings/Nodexa Agent systemd service exists. Re-run the Node Configuration command."
        fi
        ;;

    *)
        fail "Unsupported repair action."
        ;;
esac

log "Repair action '${ACTION}' completed."
