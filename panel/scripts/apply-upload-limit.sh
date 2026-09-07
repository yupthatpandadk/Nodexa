#!/usr/bin/env bash
set -euo pipefail
MB="${1:-}"
[[ "$MB" =~ ^[0-9]+$ ]] || { echo "Invalid upload limit"; exit 2; }
(( MB >= 64 && MB <= 10240 )) || { echo "Upload limit must be 64-10240 MB"; exit 2; }

# PHP: add a dedicated override to every installed FPM version.
found_php=0
for dir in /etc/php/*/fpm/conf.d; do
  [[ -d "$dir" ]] || continue
  found_php=1
  cat > "$dir/99-nodexa-upload.ini" <<EOF
upload_max_filesize = ${MB}M
post_max_size = ${MB}M
max_file_uploads = 50
EOF
done

# Nginx: one global http-context override, included by standard Debian/Ubuntu nginx.conf.
if [[ -d /etc/nginx/conf.d ]]; then
  cat > /etc/nginx/conf.d/nodexa-upload.conf <<EOF
# Managed by Nodexa Admin -> Settings -> Advanced
client_max_body_size ${MB}M;
EOF
  nginx -t
else
  echo "Nginx conf.d directory not found"
  exit 3
fi

# Reload only after configuration validation succeeded.
if command -v systemctl >/dev/null 2>&1; then
  for service in php*-fpm.service; do :; done
  while read -r unit; do [[ -n "$unit" ]] && systemctl reload "$unit" || true; done < <(systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk '{print $1}')
  systemctl reload nginx
else
  nginx -s reload
fi

echo "Applied Nodexa upload limit: ${MB} MB"
