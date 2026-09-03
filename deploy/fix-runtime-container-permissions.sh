#!/usr/bin/env bash
set -Eeuo pipefail

# Nodexa runtime permission hotfix.
# ParkerCP Yolks currently run the `container` user as uid/gid 1001.
# Older Nodexa agents hardcoded 1000:1000 for managed server data, which made
# /home/container inaccessible inside the runtime container.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
POWER_FILE="$ROOT/agent/internal/docker/power.go"

if [[ ! -f "$POWER_FILE" ]]; then
  echo "Missing $POWER_FILE" >&2
  exit 1
fi

python3 - "$POWER_FILE" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
s = p.read_text()
old = 'if err := os.Chown(path, 1000, 1000); err != nil {'
new = 'if err := os.Chown(path, 1001, 1001); err != nil {'
if old not in s:
    if new in s:
        print('Runtime ownership hotfix already applied.')
        raise SystemExit(0)
    raise SystemExit('Expected ownership line not found; power.go may have changed.')
p.write_text(s.replace(old, new, 1))
print('Updated managed runtime ownership to 1001:1001.')
PY

echo "Rebuild/redeploy nodexad after applying this hotfix."
