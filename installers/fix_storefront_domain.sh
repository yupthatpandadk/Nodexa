#!/usr/bin/env bash
# Route nordicnode.org and www.nordicnode.org to Nodexa's Laravel storefront.
# Leaves panel.nordicnode.org and pma.nordicnode.org untouched.
set -Eeuo pipefail

say()  { printf '[Nodexa Storefront] %s\n' "$*"; }
warn() { printf '[Nodexa Storefront] ADVARSEL: %s\n' "$*" >&2; }
die()  { printf '[Nodexa Storefront] FEJL: %s\n' "$*" >&2; exit 1; }

[[ "$EUID" -eq 0 ]] || die "Kør scriptet med sudo/root."
command -v nginx >/dev/null || die "Nginx er ikke installeret."
nginx -t >/dev/null 2>&1 || die "Din eksisterende Nginx-konfiguration har fejl. Ret dem før du fortsætter."

DOMAIN="${NODEXA_STOREFRONT_DOMAIN:-nordicnode.org}"
DOMAIN="${DOMAIN#www.}"
[[ "$DOMAIN" =~ ^[a-zA-Z0-9.-]+$ ]] || die "Ugyldigt storefront-domæne."
WWW="www.$DOMAIN"

PANEL_DIR=""
for candidate in "${NODEXA_PANEL_DIR:-}" /var/www/pterodactyl /var/www/nodexa/panel; do
    [[ -n "$candidate" ]] || continue
    if [[ -f "$candidate/routes/storefront.php" && -f "$candidate/public/index.php" ]]; then
        PANEL_DIR="$candidate"
        break
    fi
done
[[ -n "$PANEL_DIR" ]] || die "Kan ikke finde Nodexa. Brug NODEXA_PANEL_DIR=/sti/til/panel."
say "Storefront: $PANEL_DIR/public"

PHP_SOCK=""
for socket in /run/php/php8.3-fpm.sock /run/php/php*-fpm.sock; do
    if [[ -S "$socket" ]]; then
        PHP_SOCK="$socket"
        break
    fi
done
[[ -n "$PHP_SOCK" ]] || die "Ingen aktiv PHP-FPM socket i /run/php. Start PHP-FPM først."

CONF=/etc/nginx/sites-available/nodexa-storefront.conf
LINK=/etc/nginx/sites-enabled/nodexa-storefront.conf
BACKUP="/etc/nginx/nodexa-backups/storefront-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"

# Automatically detach the root and www names from old phpMyAdmin sites.
# Unknown sites are never rewritten: a panel vhost must remain intact.
PMA="pma.$DOMAIN"
NGINX_FILES=()
for active in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
    [[ -f "$active" ]] || continue
    target="$(readlink -f "$active")"
    [[ "$target" == "$CONF" ]] && continue
    duplicate=0
    for previous in "${NGINX_FILES[@]}"; do
        if [[ "$previous" == "$target" ]]; then duplicate=1; break; fi
    done
    if [[ "$duplicate" == 0 ]]; then NGINX_FILES+=("$target"); fi
done

python3 - "$DOMAIN" "$WWW" "$PMA" "$BACKUP" "${NGINX_FILES[@]}" <<'PY'
import hashlib
import pathlib
import re
import shutil
import sys
import tempfile

domain, www, pma, backup = sys.argv[1:5]
targets = [pathlib.Path(path) for path in sys.argv[5:]]
public = {domain, www}
server_open = re.compile(r'(?m)^\s*server\s*\{')
server_names = re.compile(r'(?m)^([ \t]*server_name\s+)([^;]+)(;)')
pma_root = re.compile(r'(?m)^\s*root\s+["\x27]?/var/www/phpmyadmin(?:/|\s|;|["\x27])')
panel_root = re.compile(r'(?m)^\s*root\s+["\x27]?/var/www/(?:pterodactyl|nodexa)(?:/|\s|;|["\x27])')

def blocks(content):
    """Yield complete server blocks, accounting for nested Nginx locations."""
    position = 0
    while match := server_open.search(content, position):
        depth, pos, quote, comment = 1, match.end(), None, False
        while pos < len(content) and depth:
            ch = content[pos]
            if comment:
                if ch == '\n': comment = False
            elif quote:
                if ch == '\\': pos += 1
                elif ch == quote: quote = None
            elif ch == '#': comment = True
            elif ch in ('"', "'"): quote = ch
            elif ch == '{': depth += 1
            elif ch == '}': depth -= 1
            pos += 1
        if depth: raise ValueError("En Nginx-serverblok er ikke afsluttet")
        yield match.start(), pos, content[match.start():pos]
        position = pos

def names_for(block):
    found = []
    for m in server_names.finditer(block):
        found.extend(re.sub(r'#.*$', '', m.group(2)).split())
    return found

original = {path: path.read_text() for path in targets}
pma_configured = any(pma in names_for(block) for txt in original.values()
                     for _, _, block in blocks(txt))
changes, unresolved = {}, []
counter = 0
for path, data in original.items():
    pieces, last = [], 0
    for start, stop, block in blocks(data):
        names = names_for(block)
        if not public.intersection(names): continue
        filename_is_pma = bool(re.search(r'(?:phpmyadmin|pma)', path.name, re.I))
        known_pma = bool(pma_root.search(block) or
                         (filename_is_pma and not panel_root.search(block)))
        if not known_pma:
            unresolved.append(str(path))
            continue
        def detach(match):
            global counter
            tokens = re.sub(r'#.*$', '', match.group(2)).split()
            if not public.intersection(tokens): return match.group(0)
            kept = [token for token in tokens if token not in public]
            if not kept:
                if pma_configured:
                    counter += 1
                    kept = [f"legacy-pma-retired-{counter}.invalid"]
                else:
                    kept = [pma]
            print(f"[Nodexa Storefront] Fjerner gammel phpMyAdmin-binding i {path}")
            return match.group(1) + " ".join(kept) + match.group(3)
        rewritten = server_names.sub(detach, block)
        pieces.extend([data[last:start], rewritten])
        last = stop
    if pieces:
        pieces.append(data[last:])
        updated = "".join(pieces)
        if updated != data: changes[path] = updated

if unresolved:
    sys.stderr.write("[Nodexa Storefront] Disse ukendte sites bruger stadig rod-domænet. "
                     "Ingen filer er ændret:\n")
    for path in sorted(set(unresolved)): sys.stderr.write(f"  {path}\n")
    sys.stderr.write("Tjek disse konfigurationer for at undgå at ødelægge andre apps.\n")
    sys.exit(3)

# Keep a copy of every edited file outside sites-enabled and a full rollback map.
manifest = pathlib.Path(backup) / 'changed-vhosts.tsv'
staged = []
for path in changes:
    saved = pathlib.Path(backup) / (hashlib.sha256(str(path).encode()).hexdigest() + '.conf')
    shutil.copy2(path, saved)
    staged.append((path, saved))
manifest.write_text("".join(f"{path}\t{saved}\n" for path, saved in staged))
try:
    for path, content in changes.items():
        with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, delete=False) as file:
            file.write(content)
            temp = pathlib.Path(file.name)
        shutil.copymode(path, temp)
        temp.replace(path)
except Exception:
    for path, saved in staged: shutil.copy2(saved, path)
    raise
print(f"[Nodexa Storefront] Rettede {len(changes)} tidligere phpMyAdmin-konfiguration(er).")
PY

OLD_CONF=0
OLD_LINK=""
if [[ -f "$CONF" ]]; then
    cp -a "$CONF" "$BACKUP/nodexa-storefront.conf"
    OLD_CONF=1
fi
if [[ -e "$LINK" || -L "$LINK" ]]; then
    OLD_LINK="$(readlink "$LINK" || true)"
    [[ -L "$LINK" ]] || die "$LINK er ikke et symlink. Ret det manuelt før fortsættelse."
fi

restore() {
    warn "Gendanner Nginx-konfigurationen fra før forsøget."
    if [[ -f "$BACKUP/changed-vhosts.tsv" ]]; then
        while read -r target saved; do
            [[ -f "$saved" ]] && cp -a "$saved" "$target"
        done < "$BACKUP/changed-vhosts.tsv"
    fi
    rm -f "$LINK"
    if [[ -n "$OLD_LINK" ]]; then ln -s "$OLD_LINK" "$LINK"; fi
    if [[ "$OLD_CONF" -eq 1 ]]; then
        cp -a "$BACKUP/nodexa-storefront.conf" "$CONF"
    else
        rm -f "$CONF"
    fi
}
cat > "$CONF" <<NGINX
# Managed by Nodexa's fix_storefront_domain.sh.
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $WWW;

    root $PANEL_DIR/public;
    index index.php;
    charset utf-8;

    access_log /var/log/nginx/nodexa-storefront.access.log;
    error_log /var/log/nginx/nodexa-storefront.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sfn "$CONF" "$LINK"

# nginx -t can exit successfully while ignoring a duplicate host. Check warnings too.
if ! nginx -t > "$BACKUP/nginx-test.txt" 2>&1 ||
   grep -Eq "conflicting server name.*($DOMAIN|$WWW)" "$BACKUP/nginx-test.txt"; then
    cat "$BACKUP/nginx-test.txt" >&2
    restore
    die "Nginx-validering fejlede; eksisterende opsætning er gendannet."
fi
if ! systemctl reload nginx; then
    restore
    nginx -t && systemctl reload nginx || true
    die "Nginx kunne ikke genindlæses; konfigurationen er gendannet."
fi

say "HTTP for $DOMAIN og $WWW peger nu på Nodexa Storefront."
say "panel.$DOMAIN og pma.$DOMAIN er ikke ændret."
say "Backupmappe: $BACKUP"

# Certbot upgrades BOTH domains to HTTPS when present. Do not silently
# claim HTTPS works if no certificate or DNS is ready.
if [[ "${NODEXA_SETUP_SSL:-1}" == "1" ]]; then
    if command -v certbot >/dev/null; then
        say "Konfigurerer HTTPS til begge storefront-domæner via Certbot..."
        if certbot --nginx --redirect -d "$DOMAIN" -d "$WWW"; then
            nginx -t && systemctl reload nginx
            say "Færdig: https://$DOMAIN og https://$WWW viser Storefront."
        else
            warn "HTTPS-opsætning mislykkedes. HTTP-vhost virker, men HTTPS skal rettes."
            warn "Kontroller DNS, port 80/443 og certifikater; kør: certbot --nginx --redirect -d $DOMAIN -d $WWW"
            exit 2
        fi
    else
        warn "Certbot findes ikke. Installer certbot/python3-certbot-nginx og kør:"
        warn "certbot --nginx --redirect -d $DOMAIN -d $WWW"
        exit 2
    fi
else
    say "HTTPS-trinnet blev fravalgt med NODEXA_SETUP_SSL=0."
fi
\t' read -r target saved; do
            [[ -f "$saved" ]] && cp -a "$saved" "$target"
        done < "$BACKUP/changed-vhosts.tsv"
    fi
    rm -f "$LINK"
    if [[ -n "$OLD_LINK" ]]; then ln -s "$OLD_LINK" "$LINK"; fi
    if [[ "$OLD_CONF" -eq 1 ]]; then
        cp -a "$BACKUP/nodexa-storefront.conf" "$CONF"
    else
        rm -f "$CONF"
    fi
}
cat > "$CONF" <<NGINX
# Managed by Nodexa's fix_storefront_domain.sh.
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $WWW;

    root $PANEL_DIR/public;
    index index.php;
    charset utf-8;

    access_log /var/log/nginx/nodexa-storefront.access.log;
    error_log /var/log/nginx/nodexa-storefront.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
NGINX
ln -sfn "$CONF" "$LINK"

# nginx -t can exit successfully while ignoring a duplicate host. Check warnings too.
if ! nginx -t > "$BACKUP/nginx-test.txt" 2>&1 ||
   grep -Eq "conflicting server name.*($DOMAIN|$WWW)" "$BACKUP/nginx-test.txt"; then
    cat "$BACKUP/nginx-test.txt" >&2
    restore
    die "Nginx-validering fejlede; eksisterende opsætning er gendannet."
fi
if ! systemctl reload nginx; then
    restore
    nginx -t && systemctl reload nginx || true
    die "Nginx kunne ikke genindlæses; konfigurationen er gendannet."
fi

say "HTTP for $DOMAIN og $WWW peger nu på Nodexa Storefront."
say "panel.$DOMAIN og pma.$DOMAIN er ikke ændret."
say "Backupmappe: $BACKUP"

# Certbot upgrades BOTH domains to HTTPS when present. Do not silently
# claim HTTPS works if no certificate or DNS is ready.
if [[ "${NODEXA_SETUP_SSL:-1}" == "1" ]]; then
    if command -v certbot >/dev/null; then
        say "Konfigurerer HTTPS til begge storefront-domæner via Certbot..."
        if certbot --nginx --redirect -d "$DOMAIN" -d "$WWW"; then
            nginx -t && systemctl reload nginx
            say "Færdig: https://$DOMAIN og https://$WWW viser Storefront."
        else
            warn "HTTPS-opsætning mislykkedes. HTTP-vhost virker, men HTTPS skal rettes."
            warn "Kontroller DNS, port 80/443 og certifikater; kør: certbot --nginx --redirect -d $DOMAIN -d $WWW"
            exit 2
        fi
    else
        warn "Certbot findes ikke. Installer certbot/python3-certbot-nginx og kør:"
        warn "certbot --nginx --redirect -d $DOMAIN -d $WWW"
        exit 2
    fi
else
    say "HTTPS-trinnet blev fravalgt med NODEXA_SETUP_SSL=0."
fi
