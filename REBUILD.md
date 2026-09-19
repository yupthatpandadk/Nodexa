# Nodexa 1.0 — Clean Rebuild

This branch is the clean rebuild track for Nodexa.

## Architecture

- **Pterodactyl-compatible panel core** for servers, nodes, allocations, users, databases, schedules, backups and Wings communication.
- **Nodexa Web** for the public hosting site, authentication, products, ordering and client area.
- **Nodexa Admin** for customers, products, orders, nodes, servers, roles, permissions, theme and system settings.
- **Nodexa Node installer** for provisioning a Wings-compatible node from a generated configuration.
- Shared Nodexa design tokens and theme across public site, client area and panel.

## Rebuild rules

1. Do not carry encrypted database values from the old installation into a fresh install.
2. Never regenerate APP_KEY on an existing installation.
3. Keep secrets and generated node tokens out of Git.
4. Keep upstream-compatible node/Wings behavior isolated from Nodexa UI customizations.
5. Updates must preserve .env, user uploads and persistent server data.
6. Installer and updater must be idempotent and fail safely.

## Initial milestones

### M1 — Foundation
- clean application structure
- environment example
- database + Redis configuration
- queue worker and scheduler
- health endpoint
- CI checks

### M2 — Authentication & Client Area
- login/register
- account/profile
- customer dashboard
- role/permission foundation

### M3 — Game Panel
- locations
- nodes
- allocations
- servers
- console/files/network/startup
- databases/schedules/backups/users

### M4 — Node deployment
- generated node configuration
- secure one-time deployment token
- install/status flow
- diagnostics

### M5 — Hosting/Commerce
- products
- configurable plans
- orders/invoices
- one-month or permanent discounts
- provisioning workflow

### M6 — Production
- Ubuntu installer
- nginx/HTTPS
- queues/scheduler
- updater/rollback
- backup/restore
- release 1.0.0

## Development branch

`nodexa-1.0-rebuild`

The existing `main` branch is intentionally left untouched while the rebuild is developed and tested.
