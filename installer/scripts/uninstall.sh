#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
ensure_ubuntu
printf 'This removes Nodexa services and application files. Database/server data is kept unless you remove it manually.\n'
read -rp "Type REMOVE to continue: " confirm
[[ "$confirm" == "REMOVE" ]] || exit 0
systemctl disable --now nodexa-agent nodexa-queue nodexa-scheduler.timer nodexa-monitor.timer 2>/dev/null || true
rm -f /etc/systemd/system/nodexa-agent.service /etc/systemd/system/nodexa-queue.service \
  /etc/systemd/system/nodexa-scheduler.service /etc/systemd/system/nodexa-scheduler.timer \
  /etc/systemd/system/nodexa-monitor.service /etc/systemd/system/nodexa-monitor.timer
rm -f /etc/nginx/sites-enabled/nodexa /etc/nginx/sites-available/nodexa
rm -f /usr/local/bin/nodexad /etc/nodexa.env
rm -rf /var/www/nodexa
systemctl daemon-reload
systemctl reload nginx 2>/dev/null || true
log "Nodexa application removed. /var/lib/nodexa and the database were preserved."
