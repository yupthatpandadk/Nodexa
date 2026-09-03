#!/usr/bin/env bash
set -Eeuo pipefail

STATE_DIR="/var/lib/nodexa"
STATE_FILE="$STATE_DIR/update-state.json"
LOG_FILE="/var/log/nodexa-update.log"
REPO="${NODEXA_UPDATE_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_UPDATE_BRANCH:-pterodactyl-core}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

mkdir -p "$STATE_DIR"
touch "$LOG_FILE"
chmod 0644 "$LOG_FILE"

now(){ date --iso-8601=seconds; }
write_state(){
  local status="$1" message="$2"
  python3 - "$STATE_FILE" "$status" "$message" "$(now)" <<'PY'
import json,sys
path,status,message,updated=sys.argv[1:]
with open(path,'w') as f:
    json.dump({'status':status,'message':message,'updated_at':updated},f,separators=(',',':'))
PY
  chmod 0644 "$STATE_FILE"
}

on_error(){
  local code=$?
  write_state "failed" "Opdateringen fejlede. Se loggen for detaljer."
  echo "[Nodexa Update] FAILED with exit code ${code}" >&2
  exit "$code"
}
trap on_error ERR

write_state "running" "Nodexa opdateres fra GitHub..."
{
  echo
  echo "============================================================"
  echo "[Nodexa Update] Started $(now)"
  echo "[Nodexa Update] Repository: ${REPO} (${BRANCH})"
  echo "============================================================"

  TMP_INSTALL="$(mktemp)"
  curl -fsSL --retry 4 --retry-delay 2 "${RAW_BASE}/install.sh" -o "$TMP_INSTALL"
  NODEXA_NONINTERACTIVE=1 \
  NODEXA_UPDATE_REPOSITORY="$REPO" \
  NODEXA_UPDATE_BRANCH="$BRANCH" \
  NODEXA_BRANCH="$BRANCH" \
    bash "$TMP_INSTALL" update </dev/null
  rm -f "$TMP_INSTALL"

  echo "[Nodexa Update] Completed $(now)"
} >> "$LOG_FILE" 2>&1

write_state "success" "Nodexa blev opdateret korrekt."
