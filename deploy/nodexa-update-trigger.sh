#!/usr/bin/env bash
set -Eeuo pipefail

SYSTEMCTL="${NODEXA_SYSTEMCTL:-/usr/bin/systemctl}"
if [[ ! -x "$SYSTEMCTL" ]]; then
  for candidate in /usr/bin/systemctl /bin/systemctl; do
    if [[ -x "$candidate" ]]; then
      SYSTEMCTL="$candidate"
      break
    fi
  done
fi

[[ -x "$SYSTEMCTL" ]] || { echo "systemctl was not found" >&2; exit 127; }
exec "$SYSTEMCTL" --no-block start nodexa-update.service
