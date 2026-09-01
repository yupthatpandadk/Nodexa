#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || exit 0
python3 - <<'PY'
from pathlib import Path
import re
for name in ('/etc/nginx/sites-available/nodexa','/etc/nginx/sites-available/nodexa-agent'):
    p=Path(name)
    if not p.exists(): continue
    text=p.read_text()
    text=re.sub(r'\n\s*client_max_body_size\s+[^;]+;','',text)
    text=re.sub(r'(server\s*\{)',r'\1\n    client_max_body_size 512m;',text,count=1)
    p.write_text(text)
for p in Path('/etc/php').glob('*/fpm/php.ini'):
    text=p.read_text()
    for key,value in [('upload_max_filesize','512M'),('post_max_size','520M'),('max_file_uploads','20')]:
        pattern=rf'^\s*;?\s*{re.escape(key)}\s*=.*$'
        repl=f'{key} = {value}'
        if re.search(pattern,text,flags=re.M): text=re.sub(pattern,repl,text,flags=re.M)
        else: text+='\n'+repl+'\n'
    p.write_text(text)
PY
for svc in $(systemctl list-unit-files --type=service --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}'); do systemctl restart "$svc" 2>/dev/null || true; done
nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
echo '[Nodexa] File-manager upload limit configured to 512 MB.'
