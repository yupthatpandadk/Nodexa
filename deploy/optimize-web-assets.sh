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

# Remove the previous managed block so repeated updates never accumulate
# duplicate performance directives.
text=re.sub(r'\n\s*# NODEXA_STATIC_BEGIN.*?# NODEXA_STATIC_END\s*\n', '\n', text, flags=re.S)

# Ubuntu 22.04/24.04 Nginx ships the HTTP/2 module. Certbot commonly leaves the
# TLS listener as plain HTTP/1.1, which makes the browser serialize more of the
# initial JS/CSS work on mobile connections.
text=re.sub(r'listen\s+443\s+ssl\s*;', 'listen 443 ssl http2;', text)
text=re.sub(r'listen\s+\[::\]:443\s+ssl\s*;', 'listen [::]:443 ssl http2;', text)

block=r'''
    # NODEXA_STATIC_BEGIN
    # Keep the critical panel bundle entirely on Nginx's static-file fast path.
    sendfile on;
    tcp_nopush on;
    keepalive_timeout 30s;
    open_file_cache max=1000 inactive=60s;
    open_file_cache_valid 120s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    # Vite filenames are content hashed. A year-long immutable cache is safe and
    # means React/vendor chunks remain cached even when the Nodexa app chunk is
    # replaced by a later update.
    location ^~ /build/ {
        try_files $uri =404;
        access_log off;
        log_not_found off;
        expires 1y;
        etag on;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
    }

    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 768;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;
    # NODEXA_STATIC_END
'''

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

echo "[Nodexa] Nginx static fast path, HTTP/2, caching and gzip are enabled."
