# Nodexa 2.0 – Client Area & VPS Management

## Shipped on main (9 November 2026)

This is the first safe, backwards-compatible stage of Nodexa 2.0.
Existing Wallet, payments, billing, subscription cancellation, 2FA,
VPS password recovery and power controls remain in place.

### Shared client navigation

- Responsive site menu when desktop links are hidden.
- Wallet balance and authenticated profile controls are retained.
- Notification bell with real unread count from `nodexa_notifications`.
- Menu links to Client Area, VPS, servers, billing, Wallet, tickets and hub.

### Client Area & account security

- Two-column compact quick links on mobile (server, VPS, EUP,
  billing, support, hub, Wallet and profile).
- Security reminder when the existing Pterodactyl 2FA flag is off.
- Unread notification count linking to the existing notification inbox.
- Profile displays existing 2FA status and up to eight own audit-log
  events; settings remain managed by the existing control panel.

### VPS Management

- Section navigation for Overview, Power, Backups, Access, Billing, Support.
- Read-only, lazy-loaded backup listing through documented reseller action
  `list_backups` with `server_id`. The API is only called after the customer
  explicitly clicks **Hent backups**.
- The API endpoint always enforces `user_id` and a valid VPS status.
  The JSON output includes only whitelisted label, date, status and size
  fields, not provider URLs, tokens or internal IDs.
- A per-VPS ticket shortcut prefills subject and server name/internal ID only
  after user ownership verification.
- A per-VPS **Anmod om opgradering** shortcut to request CPU, RAM or disk
  through Support (not an automatic upgrade or payment).

## Intentionally not enabled yet

The provider documentation shown in chat exposes `list_backups` but does
NOT document create-backup, restore-backup or delete-backup actions.
The `build` action can reinstall a VPS and destroy customer data, so it is
not exposed until request format, validation and completion behavior have been
verified on a test server. Likewise, no functioning web console API or
CPU/RAM/disk resize endpoint has been confirmed for this reseller integration.

The storefront checkout already supports Wallet-first payments, but this
release does NOT add automatic Wallet-funded subscription renewals or new
Stripe off-session payment behavior. Such a change requires a separate
billing, consent and idempotency review.

## Deployment & manual validation

Use Nodexa's normal update command:

```sh
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
```

Verify, while logged in as a test customer:

1. Mobile header: hamburger menu opens, navigation links work, Wallet and
   profile controls remain visible without horizontal overflow.
2. Client Area quick links: Wallet/profile, 2FA status and unread count render.
3. VPS detail: navigation jumps to each section.
4. **Hent backups**: returns real data (or a transparent empty/error message)
   only for the VPS owner. Try another customer's VPS ID: must return HTTP 404.
5. Support and upgrade request: new ticket opens with the correct VPS
   information, but cannot prefill an unowned service.
6. Password show/hide/copy and server power actions still behave as before.
7. No references to the upstream provider are rendered on customer pages.

Code and route consistency checks were carried out by reading the GitHub
source. Live API execution, cross-device UI testing and deployment are
separate steps and have not been claimed complete.
