#!/usr/bin/env bash
# Migrate the NordicNode/Nodexa installation to a single public domain.
# Storefront, Client Area and Control Panel all live on https://nordicnode.org.
set -Eeuo pipefail

PANEL_DIR="${NODEXA_PANEL_DIR:-/var/www/pterodactyl}"
PRIMARY_DOMAIN="${NODEXA_PRIMARY_DOMAIN:-nordicnode.org}"
PRIMARY_URL="https://${PRIMARY_DOMAIN}"
LEGACY_PANEL_DOMAIN="${NODEXA_LEGACY_PANEL_DOMAIN:-panel.${PRIMARY_DOMAIN}}"
LEGACY_PANEL_URL="https://${LEGACY_PANEL_DOMAIN}"
WINGS_CONFIG="${NODEXA_WINGS_CONFIG:-/etc/pterodactyl/config.yml}"

say(){ printf '[Nodexa Single Domain] %s\n' "$*"; }
warn(){ printf '[Nodexa Single Domain] ADVARSEL: %s\n' "$*" >&2; }
die(){ printf '[Nodexa Single Domain] FEJL: %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Kør scriptet med sudo/root."
[[ -d "${PANEL_DIR}" && -f "${PANEL_DIR}/artisan" && -f "${PANEL_DIR}/.env" ]] || die "Kan ikke finde Nodexa i ${PANEL_DIR}."

cd "${PANEL_DIR}"

CURRENT_URL="$(grep -E '^APP_URL=' .env | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
CURRENT_HOST="$(php -r 'echo strtolower((string) parse_url($argv[1], PHP_URL_HOST));' "${CURRENT_URL:-}" 2>/dev/null || true)"

case "${CURRENT_HOST}" in
  "${PRIMARY_DOMAIN}"|"${LEGACY_PANEL_DOMAIN}"|"www.${PRIMARY_DOMAIN}"|"")
    ;;
  *)
    if [[ "${NODEXA_SINGLE_DOMAIN_FORCE:-0}" != "1" ]]; then
      die "APP_URL peger på ${CURRENT_HOST}. Scriptet ændrer kun NordicNode-domæner automatisk. Sæt NODEXA_SINGLE_DOMAIN_FORCE=1 for at tvinge."
    fi
    ;;
esac

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="/root/nodexa-single-domain-${STAMP}"
mkdir -p "${BACKUP}"
chmod 700 "${BACKUP}"
cp -a .env "${BACKUP}/.env"
[[ -f "${WINGS_CONFIG}" ]] && cp -a "${WINGS_CONFIG}" "${BACKUP}/wings-config.yml"

say "Backup gemt i ${BACKUP}"
say "Sætter primært domæne til ${PRIMARY_URL}"

python3 - ".env" "${PRIMARY_URL}" "${PRIMARY_DOMAIN}" "${LEGACY_PANEL_URL}" <<'PY'
import os
import re
import stat
import sys
from pathlib import Path

path = Path(sys.argv[1])
primary = sys.argv[2]
domain = sys.argv[3]
legacy = sys.argv[4]

values = {
    "APP_URL": primary,
    "SESSION_DOMAIN": f".{domain}",
    "SESSION_SECURE_COOKIE": "true",
    "SESSION_SAME_SITE": "lax",
    "APP_CORS_ALLOWED_ORIGINS": f"{primary},https://www.{domain},{legacy}",
}

st = path.stat()
text = path.read_text()
lines = text.splitlines()
seen = set()
out = []

for line in lines:
    m = re.match(r"^([A-Z0-9_]+)=", line)
    if m and m.group(1) in values:
        key = m.group(1)
        out.append(f"{key}={values[key]}")
        seen.add(key)
    else:
        out.append(line)

if out and out[-1] != "":
    out.append("")

for key, value in values.items():
    if key not in seen:
        out.append(f"{key}={value}")

tmp = path.with_name(path.name + ".nodexa-new")
tmp.write_text("\n".join(out).rstrip() + "\n")
os.chmod(tmp, stat.S_IMODE(st.st_mode))
try:
    os.chown(tmp, st.st_uid, st.st_gid)
except PermissionError:
    pass
os.replace(tmp, path)
PY

if [[ -f "${WINGS_CONFIG}" ]]; then
    say "Kontrollerer Wings remote og WebSocket origins..."
    WINGS_BEFORE_HASH="$(sha256sum "${WINGS_CONFIG}" | awk '{print $1}')"

    python3 - "${WINGS_CONFIG}" "${PRIMARY_URL}" "${LEGACY_PANEL_URL}" <<'PY'
import os
import re
import stat
import sys
from pathlib import Path

path = Path(sys.argv[1])
primary = sys.argv[2]
legacy = sys.argv[3]
st = path.stat()
lines = path.read_text().splitlines(keepends=True)

# Remove the existing top-level allowed_origins value and any YAML list rows
# immediately belonging to it.
clean = []
i = 0
while i < len(lines):
    line = lines[i]
    if re.match(r"^allowed_origins\s*:", line):
        i += 1
        while i < len(lines) and re.match(r"^[ \t]+-[ \t]+", lines[i]):
            i += 1
        continue
    clean.append(line)
    i += 1

out = []
remote_found = False
for line in clean:
    if re.match(r"^remote\s*:", line):
        out.append(f"remote: {primary}\n")
        out.append(f'allowed_origins: ["{primary}", "{legacy}"]\n')
        remote_found = True
    else:
        out.append(line)

if not remote_found:
    if out and not out[-1].endswith("\n"):
        out[-1] += "\n"
    out.extend([
        f"remote: {primary}\n",
        f'allowed_origins: ["{primary}", "{legacy}"]\n',
    ])

tmp = path.with_name(path.name + ".nodexa-new")
tmp.write_text("".join(out))
os.chmod(tmp, stat.S_IMODE(st.st_mode))
try:
    os.chown(tmp, st.st_uid, st.st_gid)
except PermissionError:
    pass
os.replace(tmp, path)
PY

    WINGS_AFTER_HASH="$(sha256sum "${WINGS_CONFIG}" | awk '{print $1}')"

    if [[ "${WINGS_BEFORE_HASH}" == "${WINGS_AFTER_HASH}" ]]; then
        say "Wings-konfigurationen er allerede korrekt. Spring genstart over."
    elif systemctl list-unit-files wings.service >/dev/null 2>&1; then
        say "Wings-konfigurationen er ændret. Genstarter Wings..."

        if ! systemctl restart wings; then
            warn "Wings kunne ikke genstartes. Viser de seneste loglinjer:"
            journalctl -u wings -n 40 --no-pager 2>/dev/null || true
            warn "Gendanner tidligere Wings-konfiguration."
            cp -a "${BACKUP}/wings-config.yml" "${WINGS_CONFIG}"
            systemctl restart wings || true
            cp -a "${BACKUP}/.env" .env
            die "Single-domain migreringen blev rullet tilbage."
        fi

        WINGS_OK=0
        for _ in $(seq 1 30); do
            if systemctl is-active --quiet wings; then
                WINGS_OK=1
                break
            fi
            sleep 1
        done

        if [[ "${WINGS_OK}" -ne 1 ]]; then
            warn "Wings blev ikke aktiv inden for 30 sekunder. Viser de seneste loglinjer:"
            journalctl -u wings -n 40 --no-pager 2>/dev/null || true
            warn "Gendanner tidligere Wings-konfiguration."
            cp -a "${BACKUP}/wings-config.yml" "${WINGS_CONFIG}"
            systemctl restart wings || true
            cp -a "${BACKUP}/.env" .env
            die "Single-domain migreringen blev rullet tilbage."
        fi

        say "Wings er aktiv med den nye single-domain-konfiguration."
    else
        warn "wings.service blev ikke fundet. Wings-konfigurationen er ændret, men servicen kunne ikke genstartes automatisk."
    fi
else
    warn "${WINGS_CONFIG} findes ikke. APP_URL er ændret, men Wings skal have remote/allowed_origins rettet manuelt på noden."
fi

say "Rydder Laravel cache og genopbygger konfiguration..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

systemctl restart pteroq 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

EFFECTIVE_URL="$(php artisan tinker --execute="echo config('app.url');" 2>/dev/null | tail -n1 || true)"
say "Primært domæne: ${PRIMARY_URL}"
say "Legacy panel-domæne: ${LEGACY_PANEL_URL} viderestilles af Nodexa til primærdomænet."
say "Wings accepterer browser-origin fra ${PRIMARY_URL}."
say "Migration færdig."
