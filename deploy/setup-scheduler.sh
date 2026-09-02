#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo '[Nodexa] setup-scheduler.sh must run as root.' >&2; exit 1; }
PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/nodexa/panel}"
[[ -f "$PANEL_DIR/artisan" ]] || exit 0

cat > /etc/systemd/system/nodexa-scheduler.service <<EOF
[Unit]
Description=Nodexa Server Schedule Dispatcher
After=network-online.target mariadb.service redis-server.service nodexa-queue.service
Wants=network-online.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=$PANEL_DIR
ExecStart=/usr/bin/php $PANEL_DIR/artisan nodexa:schedules:run --limit=100
TimeoutStartSec=120
EOF

cat > /etc/systemd/system/nodexa-scheduler.timer <<'EOF'
[Unit]
Description=Dispatch due Nodexa server schedules every minute

[Timer]
OnBootSec=20s
OnCalendar=*-*-* *:*:00
AccuracySec=5s
Persistent=true
Unit=nodexa-scheduler.service

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now nodexa-scheduler.timer >/dev/null
systemctl restart nodexa-scheduler.timer
echo '[Nodexa] Automatic server schedules enabled through the Laravel queue.'
