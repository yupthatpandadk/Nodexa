#!/usr/bin/env bash
set -Eeuo pipefail

[[ $EUID -eq 0 ]] || { echo "[Nodexa] optimize-web-assets.sh must run as root." >&2; exit 1; }

AVAILABLE="/etc/nginx/sites-available/nodexa"
ENABLED="/etc/nginx/sites-enabled/nodexa"

optimize_file(){
  local file="$1"
  [[ -f "$file" ]] || return 0
  python3 - "$file" <<'PY'
from pathlib import Path
import re, sys
p=Path(sys.argv[1])
text=p.read_text()

# Remove an earlier Nodexa-managed optimization block so the updater is
# idempotent and never accumulates duplicate Nginx directives.
text=re.sub(r'\n\s*# NODEXA_STATIC_BEGIN.*?# NODEXA_STATIC_END\s*\n', '\n', text, flags=re.S)

block=r'''
    # NODEXA_STATIC_BEGIN
    # Vite filenames are content hashed, so they can safely stay in the browser
    # cache for a year. This avoids downloading/revalidating the React bundle on
    # every page load and, importantly, prevents a missing static asset from
    # falling through to Laravel/PHP.
    location ^~ /build/ {
        try_files $uri =404;
        access_log off;
        log_not_found off;
        expires 1y;
        etag on;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
    }

    # Compress the JS/CSS/JSON responses on first download. Subsequent loads are
    # served directly from the browser cache because of the immutable rule above.
    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;
    # NODEXA_STATIC_END
'''

# Insert into every server block that serves the Nodexa public directory. There
# is normally one block; Certbot may add SSL directives to that same block.
needle=re.compile(r'(root\s+[^;]*?/panel/public\s*;)')
if needle.search(text):
    text=needle.sub(lambda m: m.group(1)+"\n"+block.rstrip(), text)
elif 'location / {' in text:
    text=text.replace('location / {', block+'\n    location / {', 1)
else:
    raise SystemExit('Could not locate Nodexa Nginx server block')

p.write_text(text)
PY
}

optimize_file "$AVAILABLE"
if [[ -e "$ENABLED" || -L "$ENABLED" ]]; then
  if [[ -L "$ENABLED" ]]; then
    a="$(readlink -f "$AVAILABLE" 2>/dev/null || true)"
    e="$(readlink -f "$ENABLED" 2>/dev/null || true)"
    [[ -n "$e" && "$e" == "$a" ]] || optimize_file "$ENABLED"
  else
    optimize_file "$ENABLED"
  fi
fi

nginx -t
systemctl reload nginx

echo "[Nodexa] Vite assets now use immutable browser caching and gzip compression."
