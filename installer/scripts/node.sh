#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/common.sh"
prepare_source

ask_yes_no_node(){ local prompt="$1" default_value="${2:-y}" answer; read -rp "$prompt" answer || true; answer="${answer:-$default_value}"; case "$answer" in y|Y|yes|YES|Yes) return 0;; *) return 1;; esac; }
ask_secret_node(){ local prompt="$1" var_name="$2" value; read -srp "$prompt" value || true; echo; printf -v "$var_name" '%s' "$value"; }
normalize_host(){ local value="$1"; value="${value#http://}"; value="${value#https://}"; value="${value%%/*}"; value="${value%%:*}"; printf '%s' "$value"; }
valid_fqdn(){ [[ "$1" =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]; }

TOKEN="${NODEXA_AGENT_TOKEN:-}"
PUBLIC_PORT="${NODEXA_AGENT_PUBLIC_PORT:-${NODEXA_AGENT_PORT:-8080}}"
INTERNAL_PORT="${NODEXA_AGENT_INTERNAL_PORT:-${NODEXA_AGENT_PORT:-8080}}"
SFTP_PORT="${NODEXA_SFTP_PORT:-2022}"
PANEL_URL="${NODEXA_PANEL_URL:-}"
PANEL_HOST="$(normalize_host "$PANEL_URL")"
AGENT_FQDN="${NODEXA_AGENT_FQDN:-}"
ENABLE_HTTPS="${NODEXA_AGENT_HTTPS:-0}"
CONFIGURE_UFW="${NODEXA_CONFIGURE_UFW:-}"
CONFIGURE_DB_HOST="${NODEXA_CONFIGURE_DB_HOST:-}"
DB_EXTERNAL="${NODEXA_DB_EXTERNAL:-}"
DB_ALLOW_3306="${NODEXA_DB_ALLOW_3306:-}"
DB_PANEL_SOURCE="${NODEXA_DB_PANEL_SOURCE:-}"
DB_HOST_USER="${NODEXA_DB_HOST_USER:-nodexa_dbhost}"
DB_HOST_PASSWORD="${NODEXA_DB_HOST_PASSWORD:-}"
SSL_EMAIL="${NODEXA_SSL_EMAIL:-}"

clear 2>/dev/null || true
cat <<'BANNER'
============================================================
                Nodexa Agent / Node Setup
============================================================
This installs the Nodexa Agent, Docker and optional services
needed by a game-server node.

The Agent will not be started without a configuration token
from Nodexa Panel. Swap is not enabled automatically.
BANNER

echo
if [[ -z "$CONFIGURE_UFW" ]]; then if ask_yes_no_node "Automatically configure UFW firewall? [Y/n]: " y; then CONFIGURE_UFW=1; else CONFIGURE_UFW=0; fi; fi
if [[ -z "$CONFIGURE_DB_HOST" ]]; then if ask_yes_no_node "Configure this Node as a database host too? [y/N]: " n; then CONFIGURE_DB_HOST=1; else CONFIGURE_DB_HOST=0; fi; fi
if [[ "$CONFIGURE_DB_HOST" == "1" ]]; then
 echo; echo "Database Host"; echo "------------------------------------------------------------"
 if [[ -z "$DB_EXTERNAL" ]]; then if ask_yes_no_node "Allow MySQL/MariaDB to accept remote connections? [Y/n]: " y; then DB_EXTERNAL=1; else DB_EXTERNAL=0; fi; fi
 if [[ "$DB_EXTERNAL" == "1" ]]; then
  if [[ -z "$DB_PANEL_SOURCE" ]]; then read -rp "Panel IP/hostname allowed to reach MySQL [${PANEL_HOST:-any}]: " DB_PANEL_SOURCE || true; fi
  DB_PANEL_SOURCE="${DB_PANEL_SOURCE:-${PANEL_HOST:-%}}"
  echo; echo "WARNING: Port 3306 should not be exposed to the whole internet."; echo "Nodexa can restrict UFW and the database account to your panel host/IP."
  if [[ -z "$DB_ALLOW_3306" ]]; then if ask_yes_no_node "Open MySQL port 3306 for the configured panel source? [Y/n]: " y; then DB_ALLOW_3306=1; else DB_ALLOW_3306=0; fi; fi
 fi
 read -rp "Database host username [${DB_HOST_USER}]: " _dbuser || true; DB_HOST_USER="${_dbuser:-$DB_HOST_USER}"
 if [[ -z "$DB_HOST_PASSWORD" ]]; then ask_secret_node "Database host password (blank = generate secure password): " DB_HOST_PASSWORD; fi
 if [[ -z "$DB_HOST_PASSWORD" ]]; then DB_HOST_PASSWORD="$(openssl rand -base64 36 | tr -dc 'A-Za-z0-9!@#%_-.' | head -c 28)"; DB_HOST_PASSWORD_GENERATED=1; else DB_HOST_PASSWORD_GENERATED=0; fi
fi

if [[ -z "$AGENT_FQDN" && -t 0 ]]; then
 echo; echo "HTTPS / Node FQDN"; echo "------------------------------------------------------------"; echo "A Let's Encrypt certificate requires a real FQDN, not a raw IP address."
 if ask_yes_no_node "Configure HTTPS for the Node with Let's Encrypt? [y/N]: " n; then ENABLE_HTTPS=1; else ENABLE_HTTPS=0; fi
 if [[ "$ENABLE_HTTPS" == "1" ]]; then while true; do read -rp "Node FQDN (example node.example.com): " AGENT_FQDN || true; AGENT_FQDN="$(normalize_host "$AGENT_FQDN")"; valid_fqdn "$AGENT_FQDN" && break; echo "Please enter a valid FQDN."; done; fi
fi
if [[ "$ENABLE_HTTPS" == "1" ]]; then
 [[ -n "$AGENT_FQDN" ]] || fail "HTTPS was requested but no Node FQDN was provided."; valid_fqdn "$AGENT_FQDN" || fail "Invalid Node FQDN: $AGENT_FQDN"; PUBLIC_PORT="${NODEXA_AGENT_PUBLIC_PORT:-443}"; INTERNAL_PORT="${NODEXA_AGENT_INTERNAL_PORT:-8080}"
 if [[ -z "$SSL_EMAIL" && -t 0 ]]; then read -rp "Let's Encrypt email [${NODEXA_ADMIN_EMAIL:-}]: " SSL_EMAIL || true; fi; SSL_EMAIL="${SSL_EMAIL:-${NODEXA_ADMIN_EMAIL:-}}"; [[ "$SSL_EMAIL" == *@*.* ]] || fail "A valid Let's Encrypt email is required for HTTPS."
fi

echo; echo "============================================================"; echo "Node configuration summary"; echo "============================================================"
echo "Agent token:       $([[ -n "$TOKEN" ]] && echo 'Provided by Nodexa Panel' || echo 'Not provided — Agent will wait')"
echo "Agent listen port: ${INTERNAL_PORT}"; echo "Public port:       ${PUBLIC_PORT}"; echo "SFTP port:         ${SFTP_PORT}"; echo "Node FQDN:         ${AGENT_FQDN:-not configured}"; echo "HTTPS:             $([[ "$ENABLE_HTTPS" == "1" ]] && echo "Let's Encrypt" || echo 'Disabled')"; echo "UFW:               $([[ "$CONFIGURE_UFW" == "1" ]] && echo 'Configure automatically' || echo 'Skip')"; echo "Database host:     $([[ "$CONFIGURE_DB_HOST" == "1" ]] && echo "$DB_HOST_USER" || echo 'Not configured')"
if [[ "$CONFIGURE_DB_HOST" == "1" && "$DB_EXTERNAL" == "1" ]]; then echo "MySQL source:      ${DB_PANEL_SOURCE:-%}"; fi
echo; if [[ -t 0 ]]; then if ! ask_yes_no_node "Install Nodexa Agent with these settings? [Y/n]: " y; then echo "Installation cancelled."; exit 0; fi; fi

log "Installing Nodexa Agent..."; apt-get update -y; apt-get install -y ca-certificates curl tar build-essential pkg-config docker.io openssl nginx; systemctl enable --now docker
if [[ "$CONFIGURE_DB_HOST" == "1" ]]; then
 log "Installing and configuring MariaDB database host..."; apt-get install -y mariadb-server; systemctl enable --now mariadb
 if [[ "$DB_EXTERNAL" == "1" ]]; then printf '[mysqld]\nbind-address = 0.0.0.0\n' >/etc/mysql/mariadb.conf.d/60-nodexa-dbhost.cnf; systemctl restart mariadb; fi
 MYSQL_ACCOUNT_HOST="localhost"; if [[ "$DB_EXTERNAL" == "1" ]]; then MYSQL_ACCOUNT_HOST="${DB_PANEL_SOURCE:-%}"; [[ "$MYSQL_ACCOUNT_HOST" == "any" ]] && MYSQL_ACCOUNT_HOST="%"; fi
 DB_USER_SQL="$(printf '%s' "$DB_HOST_USER" | sed "s/'/''/g")"; DB_PASS_SQL="$(printf '%s' "$DB_HOST_PASSWORD" | sed "s/'/''/g")"; DB_HOST_SQL="$(printf '%s' "$MYSQL_ACCOUNT_HOST" | sed "s/'/''/g")"
 mysql -uroot <<SQL
CREATE USER IF NOT EXISTS '${DB_USER_SQL}'@'${DB_HOST_SQL}' IDENTIFIED BY '${DB_PASS_SQL}';
ALTER USER '${DB_USER_SQL}'@'${DB_HOST_SQL}' IDENTIFIED BY '${DB_PASS_SQL}';
GRANT ALL PRIVILEGES ON *.* TO '${DB_USER_SQL}'@'${DB_HOST_SQL}' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
fi
if [[ "$CONFIGURE_UFW" == "1" ]]; then
 log "Configuring UFW..."; apt-get install -y ufw; ufw allow OpenSSH >/dev/null; ufw allow "${SFTP_PORT}/tcp" >/dev/null
 if [[ "$ENABLE_HTTPS" == "1" ]]; then ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null; else ufw allow "${PUBLIC_PORT}/tcp" >/dev/null; fi
 if [[ "$CONFIGURE_DB_HOST" == "1" && "$DB_EXTERNAL" == "1" && "$DB_ALLOW_3306" == "1" ]]; then
  if [[ "$DB_PANEL_SOURCE" =~ ^[0-9a-fA-F:.]+$ ]]; then ufw allow from "$DB_PANEL_SOURCE" to any port 3306 proto tcp >/dev/null; else RESOLVED_PANEL_IP="$(getent ahostsv4 "$DB_PANEL_SOURCE" 2>/dev/null | awk 'NR==1{print $1}' || true)"; if [[ -n "$RESOLVED_PANEL_IP" ]]; then ufw allow from "$RESOLVED_PANEL_IP" to any port 3306 proto tcp >/dev/null; else echo "[WARN] Could not resolve $DB_PANEL_SOURCE; port 3306 was NOT opened by UFW."; fi; fi
 fi
 ufw --force enable >/dev/null
fi

ARCH="$(dpkg --print-architecture)"; case "$ARCH" in amd64) GOARCH=amd64;; arm64) GOARCH=arm64;; *) fail "Unsupported architecture: $ARCH";; esac
NEED_GO=1; if command -v go >/dev/null 2>&1; then GOV="$(go version | awk '{print $3}' | sed 's/^go//')"; if printf '%s\n%s\n' "1.23" "$GOV" | sort -V -C 2>/dev/null; then NEED_GO=0; fi; fi
if [[ "$NEED_GO" == "1" ]]; then GO_VERSION="${NODEXA_GO_VERSION:-1.23.12}"; curl -fL "https://go.dev/dl/go${GO_VERSION}.linux-${GOARCH}.tar.gz" -o /tmp/nodexa-go.tgz; rm -rf /usr/local/go && tar -C /usr/local -xzf /tmp/nodexa-go.tgz; ln -sf /usr/local/go/bin/go /usr/local/bin/go; fi
install -d /var/www/nodexa/agent /var/lib/nodexa /var/lib/nodexa/servers /var/lib/nodexa/backups
rm -rf /var/www/nodexa/agent/*; cp -a "$SOURCE_ROOT/agent/." /var/www/nodexa/agent/; cd /var/www/nodexa/agent; /usr/local/bin/go mod tidy || go mod tidy; /usr/local/bin/go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad || go build -trimpath -ldflags='-s -w' -o /usr/local/bin/nodexad ./cmd/nodexad
cat >/etc/nodexa.env <<ENV
NODEXA_LISTEN=127.0.0.1:${INTERNAL_PORT}
NODEXA_TOKEN=${TOKEN}
NODEXA_DATA=/var/lib/nodexa/servers
NODEXA_BACKUPS=/var/lib/nodexa/backups
NODEXA_PANEL_URL=${PANEL_URL}
NODEXA_SFTP_LISTEN=0.0.0.0:${SFTP_PORT}
NODEXA_SFTP_HOST_KEY=/var/lib/nodexa/sftp_host_ed25519
NODEXA_SFTP_CREDENTIALS=/var/lib/nodexa/sftp_credentials.json
ENV
if [[ "$ENABLE_HTTPS" != "1" ]]; then sed -i "s/^NODEXA_LISTEN=.*/NODEXA_LISTEN=0.0.0.0:${INTERNAL_PORT}/" /etc/nodexa.env; fi
chmod 600 /etc/nodexa.env
cat >/etc/systemd/system/nodexa-agent.service <<'UNIT'
[Unit]
Description=Nodexa Agent
After=network-online.target docker.service
Requires=docker.service
Wants=network-online.target

[Service]
Type=simple
EnvironmentFile=/etc/nodexa.env
ExecStart=/usr/local/bin/nodexad
Restart=always
RestartSec=3
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload

if [[ "$ENABLE_HTTPS" == "1" ]]; then
 log "Configuring HTTPS reverse proxy for ${AGENT_FQDN}..."
 cat >/etc/nginx/sites-available/nodexa-agent <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${AGENT_FQDN};
    location / {
        proxy_pass http://127.0.0.1:${INTERNAL_PORT};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }
}
NGINX
 ln -sf /etc/nginx/sites-available/nodexa-agent /etc/nginx/sites-enabled/nodexa-agent; rm -f /etc/nginx/sites-enabled/default; nginx -t; systemctl enable --now nginx; systemctl reload nginx
 RESOLVED="$(getent ahostsv4 "$AGENT_FQDN" 2>/dev/null | awk '{print $1}' | sort -u | tr '\n' ' ' || true)"
 if [[ -z "$RESOLVED" ]]; then echo "[WARN] ${AGENT_FQDN} does not resolve yet. HTTPS certificate was not requested."; echo "After DNS propagates run: NODEXA_DOMAIN=${AGENT_FQDN} NODEXA_ENABLE_SSL=1 NODEXA_SSL_EMAIL=${SSL_EMAIL} nodexa-ssl-setup"; else apt-get install -y certbot python3-certbot-nginx; if certbot --nginx --non-interactive --agree-tos --email "$SSL_EMAIL" --redirect --no-eff-email -d "$AGENT_FQDN"; then systemctl enable --now certbot.timer >/dev/null 2>&1 || true; log "HTTPS active for Nodexa Agent: https://${AGENT_FQDN}:${PUBLIC_PORT}"; else echo "[WARN] Let's Encrypt failed. Check DNS and ports 80/443. Agent installation will continue."; fi; fi
fi
if [[ "$CONFIGURE_DB_HOST" == "1" ]]; then install -d -m 0700 /root/.nodexa; cat >/root/.nodexa/database-host.txt <<EOF
Nodexa Database Host
Host: ${AGENT_FQDN:-$(hostname -I | awk '{print $1}')}
Port: 3306
Username: ${DB_HOST_USER}
Password: ${DB_HOST_PASSWORD}
Allowed source: ${DB_PANEL_SOURCE:-localhost}
EOF
 chmod 0600 /root/.nodexa/database-host.txt; fi
if [[ -n "$TOKEN" ]]; then systemctl enable --now nodexa-agent; sleep 1; if systemctl is-active --quiet nodexa-agent; then log "Nodexa Agent installed and started successfully. SFTP is listening on port ${SFTP_PORT}."; else echo "[WARN] Nodexa Agent did not stay active. Check: journalctl -u nodexa-agent -n 100 --no-pager"; fi; else systemctl disable --now nodexa-agent 2>/dev/null || true; log "Nodexa Agent installed but is WAITING FOR CONFIGURATION."; echo "Create the Node in Nodexa Panel: Admin → Nodes → Create Node"; echo "Then run the generated installation command from the Node configuration page."; fi
if [[ "$CONFIGURE_DB_HOST" == "1" ]]; then echo; echo "Database host credentials are stored root-only in:"; echo "  /root/.nodexa/database-host.txt"; fi
