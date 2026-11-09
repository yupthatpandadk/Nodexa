# Nodexa · Proxmox VE integration

## Scope (initial integration)

Owner-only connection management and read-only inventory for Proxmox VE
clusters. Supports multiple Proxmox API connections with separately encrypted
secrets. Existing Flax-powered customer VPS orders, Wallet, billing and server
management are unchanged.

Available in **Nodexa Admin → Proxmox VE** (\`/admin/proxmox\`).

- Create/edit/delete a Proxmox connection.
- Test the API connection and manually refresh node and VM/LXC inventory.
- View node status, CPU and memory, plus guest ID/name/node/type/status/CPU/RAM.
- Tokens are encrypted with Laravel Crypt; they are never returned in HTML.
- Login is required by the existing admin route group and this controller
  additionally enforces the Pterodactyl \`root_admin\` flag. Other custom
  admin/staff roles cannot access or change Proxmox credentials.
- No power control, guest creation, deletion, installation, IP assignment,
  invoicing or customer-to-VM mapping is enabled in this phase.

## Create a least-privilege Proxmox API token

On Proxmox VE (8.x/9.x):

1. Datacenter → Permissions → Users: create a dedicated service user, e.g.
   \`nodexa@pve\`. Do not use full root permissions unless necessary.
2. Datacenter → Permissions → API Tokens: create a token for that user,
   e.g. \`monitor\`, with **Privilege Separation** left enabled.
3. Grant the user and the token the necessary read-only permissions on \`/\`.
   The built-in **PVEAuditor** role is a convenient read-only starting point
   (including Sys.Audit and VM.Audit).
4. Copy the token secret immediately. Proxmox displays it only once.
5. In Nodexa Admin → Proxmox VE, enter:
   - Name: \`Hetzner Proxmox\` or your preferred label
   - API URL: \`https://pve.example.dk:8006\` or \`https://192.168.1.20:8006\`
   - Token ID: \`nodexa@pve!monitor\`
   - Token secret: the copied Proxmox token secret
6. Leave TLS verification **enabled** (recommended). Install a trusted HTTPS
   certificate or make your Proxmox CA trusted by the Nodexa host. If using a
   self-signed certificate on a trusted network, the owner may choose to turn
   verification off; this weakens transport security and is not recommended.
7. Save, then click **Test & synkronisér**. Nodexa will read:
   - \`GET /api2/json/nodes\`
   - \`GET /api2/json/cluster/resources?type=vm\`

The Nodexa server must have TCP connectivity to the Proxmox API's HTTPS port.
Avoid publicly exposing port 8006 if a VPN or restricted admin network can be
used. A reachable private LAN address is supported by this owner-only feature.

## Backend safety and connection details

The Proxmox authentication header is:
\`Authorization: PVEAPIToken=USER@REALM!TOKENID=SECRET\`.
The entire header and secret are used **only** server-side, never in URLs,
logs, notifications, audit metadata or HTML. API calls have bounded timeouts
and do not follow redirects. TLS verification defaults to enabled.

External endpoints are limited to HTTPS on port 8006 or 443; URL paths,
embedded credentials and query strings are rejected. Loopback and link-local
IP addresses are rejected. Proxmox private LAN addresses are intentionally
allowed because this is an owner-only integration. Do not expose this
configuration interface to untrusted staff accounts.

Server data stored as snapshots is restricted to safe, non-credential node
and guest metadata. Snapshots are refreshed manually and have a visible
timestamp. Updating connection settings invalidates an old snapshot.

## Deployment

Update Nodexa using the existing installer, which must run new migrations:

\`\`\`bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
\`\`\`

If migrations are not automatically applied:

\`\`\`bash
cd /var/www/pterodactyl
sudo php artisan migrate --force
sudo php artisan optimize:clear
\`\`\`

Open \`/admin/proxmox\` as a root-admin account.

## Validation checklist

- A non-root staff/admin user must get HTTP 403 on every Proxmox page/action.
- Wrong API token: connection test shows a safe failure.
- Token with insufficient permissions: connection test explains 403.
- Correct token: list all permitted nodes, QEMU VMs and LXC containers.
- Disconnect: remove only the Nodexa connection, never any Proxmox machines.
- After updating endpoint or token: old snapshots disappear until retested.
- Other Nodexa products and customer pages continue operating normally.

This code has been reviewed against repository structure; active API
connectivity and real credentials have not been tested.

Proxmox official API token documentation:
https://github.com/proxmox/pve-docs/blob/master/pveum.adoc
API viewer:
https://pve.proxmox.com/pve-docs/api-viewer/
