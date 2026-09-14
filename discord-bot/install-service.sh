#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-/var/www/nodexa}"
cd "$ROOT/discord-bot"
npm install --omit=dev
if [ ! -f .env ]; then cp .env.example .env; echo "Edit $ROOT/discord-bot/.env before starting the service."; fi
sed "s#/var/www/nodexa#$ROOT#g" nodexa-discord.service > /etc/systemd/system/nodexa-discord.service
systemctl daemon-reload
systemctl enable nodexa-discord.service
echo "Installed. Configure .env, then run: systemctl restart nodexa-discord"
