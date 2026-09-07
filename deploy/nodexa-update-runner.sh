#!/usr/bin/env bash
set -Eeuo pipefail
STATE_DIR="/var/lib/nodexa"; STATE_FILE="$STATE_DIR/update-state.json"; LOG_FILE="/var/log/nodexa-update.log"
REPO="${NODEXA_UPDATE_REPOSITORY:-yupthatpandadk/Nodexa}"; BRANCH="${NODEXA_UPDATE_BRANCH:-main}"; RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
mkdir -p "$STATE_DIR"; touch "$LOG_FILE"; chmod 0644 "$LOG_FILE"
now(){ date --iso-8601=seconds; }
write_state(){ local status="$1" message="$2" progress="${3:-0}" step="${4:-}"; python3 - "$STATE_FILE" "$status" "$message" "$(now)" "$progress" "$step" <<'PY'
import json,sys
path,status,message,updated,progress,step=sys.argv[1:]
with open(path,'w') as f: json.dump({'status':status,'message':message,'updated_at':updated,'progress':max(0,min(100,int(progress))),'step':step},f,separators=(',',':'))
PY
chmod 0644 "$STATE_FILE"; }
on_error(){ local code=$?; write_state failed "Opdateringen fejlede. Se loggen for detaljer." 100 "Fejl"; echo "[Nodexa Update] FAILED with exit code ${code}" >&2; exit "$code"; }
trap on_error ERR
write_state running "Forbereder Nodexa-opdateringen..." 5 "Forbereder"
{
echo; echo "============================================================"; echo "[Nodexa Update] Started $(now)"; echo "[Nodexa Update] Repository: ${REPO} (${BRANCH})"; echo "============================================================"
write_state running "Downloader den nyeste updater..." 12 "Downloader"
TMP_INSTALL="$(mktemp)"; curl -fsSL --retry 4 --retry-delay 2 "${RAW_BASE}/install.sh" -o "$TMP_INSTALL"
write_state running "Updateren er downloadet. Starter installation..." 20 "Starter"
# Watch updater output and translate completed phases into progress. Progress only advances when real output is observed.
NODEXA_NONINTERACTIVE=1 NODEXA_UPDATE_REPOSITORY="$REPO" NODEXA_UPDATE_BRANCH="$BRANCH" NODEXA_BRANCH="$BRANCH" bash "$TMP_INSTALL" update </dev/null 2>&1 | while IFS= read -r line; do
 echo "$line"
 case "$line" in
  *"Updating panel files"*) write_state running "Opdaterer Nodexa-filer..." 30 "Filer" ;;
  *"Installing locked PHP dependencies"*) write_state running "Installerer PHP dependencies..." 42 "Dependencies" ;;
  *"Migrat"*|*"migrat"*) write_state running "Opdaterer databasen..." 55 "Database" ;;
  *"storage link"*|*"Storage link"*) write_state running "Kontrollerer storage og rettigheder..." 62 "Storage" ;;
  *"npm install"*|*"packages"*) write_state running "Installerer frontend dependencies..." 70 "Frontend" ;;
  *"building"*|*"built"*|*"webpack"*|*"vite"*) write_state running "Bygger Nodexa frontend..." 80 "Frontend build" ;;
  *"setup-updater"*|*"Panel updater"*) write_state running "Opdaterer systemintegration..." 88 "System" ;;
  *"Nodexa Agent"*|*"Wings"*) write_state running "Kontrollerer Nodexa Agent..." 92 "Agent" ;;
  *"Update complete"*) write_state running "Afslutter og genindlæser services..." 97 "Afslutter" ;;
 esac
done
rm -f "$TMP_INSTALL"
echo "[Nodexa Update] Completed $(now)"
} >> "$LOG_FILE" 2>&1
write_state success "Nodexa blev opdateret korrekt." 100 "Færdig"
