#!/usr/bin/env bash
set -Eeuo pipefail

STATE_DIR="/var/lib/nodexa"
STATE_FILE="$STATE_DIR/update-state.json"
LOG_FILE="/var/log/nodexa-update.log"
REPO="${NODEXA_UPDATE_REPOSITORY:-yupthatpandadk/Nodexa}"
BRANCH="${NODEXA_UPDATE_BRANCH:-main}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

mkdir -p "$STATE_DIR"
touch "$LOG_FILE"
chmod 0644 "$LOG_FILE"

now(){ date --iso-8601=seconds; }
write_state(){
  local status="$1" message="$2"
  printf '{"status":"%s","message":"%s","updated_at":"%s"}\n' "$status" "$message" "$(now)" > "$STATE_FILE"
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

  bash <(curl -fsSL "${RAW_BASE}/install.sh") update

  echo "[Nodexa Update] Completed $(now)"
} >> "$LOG_FILE" 2>&1

write_state "success" "Nodexa blev opdateret korrekt."
