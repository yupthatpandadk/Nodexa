# Nodexa — Pterodactyl Feature Parity

Goal: implement the useful game-server management capabilities users expect from Pterodactyl while keeping Nodexa's own branding, UI, architecture and code.

## Customer server workspace

- [x] Console output, commands and power controls
- [x] Runtime CPU/RAM/network statistics
- [x] File Agent API: list/read/write/upload/download/mkdir/rename/archive/extract/delete
- [x] Replace Files placeholder with complete React file manager
- [x] Database create/list/delete and isolated credentials
- [x] Complete credential reveal/rotation UI and database workspace polish
- [x] Subuser backend and permissions
- [x] Complete permission editor for existing subusers
- [x] Backup Agent API: list/create/download/restore/delete
- [x] Replace Backups placeholder with complete React backup manager
- [x] Schedule backend foundation
- [x] Replace Schedules placeholder with complete React scheduler
- [x] Background scheduler worker with next-run calculation, task chains and online-only rules
- [x] Network/allocation management UI and API
- [x] Primary/additional allocation assignment
- [ ] Startup variables visible to customers when permitted
- [ ] Customer-editable egg variables with validation/rules
- [x] Server rename/reinstall controls in React settings
- [ ] Activity/audit log per server
- [ ] WebSocket/SSE realtime console and stats
- [ ] True stdin attachment instead of shell-exec command emulation
- [ ] SFTP subsystem compatible with per-server credentials

## Provisioning / Eggs

- [ ] Nests/categories
- [ ] Eggs/templates
- [ ] Egg import/export
- [ ] Docker image definitions and selectable images
- [ ] Startup command templates
- [ ] Environment variables, defaults, validation and user-viewable/editable flags
- [ ] Installation scripts with install container/image
- [ ] Installation status, failure state and reinstall
- [ ] Config-file parsers/replacements
- [ ] File denylist / protected paths where appropriate
- [ ] Dependency/feature relationships between templates

## Admin server management

- [x] Admin can see all servers
- [x] Server numbering/identifiers
- [x] Admin-only startup foundation
- [ ] Full create-server wizard
- [ ] Suspend / unsuspend
- [ ] Delete safely with Agent cleanup
- [ ] Transfer server between Nodes
- [ ] Change owner
- [ ] Change resource build limits
- [ ] Change Docker image/startup/variables
- [ ] Manage allocations
- [ ] Manage databases
- [ ] Manage mounts
- [ ] Manage server subusers
- [ ] Reinstall from admin UI

## Nodes

- [x] Node model, create/config/token and Nodexa Agent installer
- [x] Agent health/error diagnostics foundation
- [x] Real online/offline state in React (never hardcoded)
- [ ] Node detail page
- [ ] Node resource usage/capacity
- [ ] Node allocations/IP aliases/port ranges
- [ ] Automatic allocation creation ranges
- [ ] Node configuration YAML/view/copy workflow
- [ ] Token rotation workflow with status
- [ ] Node server list
- [ ] Node maintenance/drain mode
- [ ] Node heartbeat/version compatibility
- [ ] Node transfer queue

## Locations

- [ ] Location CRUD
- [ ] Associate Nodes with locations
- [ ] Location-aware provisioning

## Users

- [x] Accounts/login/Sanctum authentication
- [x] Admin flag and subusers
- [ ] Admin user CRUD
- [ ] Password reset flow
- [ ] Two-factor authentication + recovery codes
- [ ] Account API keys
- [ ] Per-user language/timezone/preferences
- [ ] Account activity/security log

## Databases / database hosts

- [x] Database hosts foundation
- [x] Per-database isolated users
- [x] Encrypted stored credentials
- [x] One-time database workspace gateway foundation
- [ ] Multiple database hosts per Node/location
- [ ] Host health/capacity checks
- [ ] Database limits per server
- [x] Rotate credentials
- [ ] Better remote-host TLS/security options

## Backups

- [x] Local Agent backup primitives
- [ ] Backup limits
- [ ] Locked backups
- [ ] Progress/status
- [ ] Checksums
- [ ] S3-compatible backup storage
- [ ] Restore safety/maintenance state
- [x] Scheduled backups

## Mounts / storage

- [ ] Mount CRUD
- [ ] Node/egg/server mount assignment
- [ ] Read-only/read-write mount policy
- [ ] Safe Agent bind-mount implementation

## Admin platform

- [ ] Admin dashboard with actual fleet metrics
- [ ] Settings: company/panel URL/timezone/locale
- [ ] Mail configuration + test mail
- [ ] Captcha/security configuration
- [ ] Rate-limit/security settings
- [ ] Theme/branding settings
- [ ] Application API keys
- [ ] Role/permission system for staff (beyond single is_admin flag)
- [ ] Full audit/activity logs
- [x] System issue diagnostics foundation
- [x] Update system foundation

## API

- [ ] Stable versioned Client API
- [ ] Stable versioned Application/Admin API
- [ ] API key scopes/permissions
- [ ] Pagination/filter/sort conventions
- [ ] Rate limiting
- [ ] OpenAPI documentation

## Agent parity / production runtime

- [x] Docker create/start/stop/restart/kill/stats/logs
- [x] Managed installation/reinstallation foundation
- [x] Files and local backups primitives
- [ ] Realtime WebSocket console
- [ ] Attach stdin to game process
- [ ] Server resource enforcement parity: CPU, memory, swap, IO/PIDs
- [ ] Network/allocation bindings
- [ ] Docker image pull/update status
- [ ] Container event monitoring
- [ ] Crash detection/recovery policy
- [ ] Disk usage/quota enforcement
- [ ] SFTP server
- [ ] Backup concurrency/locks
- [ ] Transfer protocol between Agents
- [ ] Install-script isolation and progress events
- [ ] Metrics/heartbeat/version endpoint
- [ ] Graceful Agent restart without losing managed state

## Nodexa-specific quality requirements

- Mobile-first responsive UI.
- No copied Pterodactyl branding/assets/source code.
- No hardcoded Online states.
- Secrets never appear in URLs or logs.
- Customer actions are permission checked server-side.
- Destructive actions require explicit confirmation.
- CI must typecheck/build React, lint PHP syntax, boot/migrate Laravel and build/test Agent.

This file is the parity checklist. A checkbox is only marked complete when the feature is connected end-to-end (Panel API + Agent where needed + permission checks + usable UI), not merely when a placeholder exists.
