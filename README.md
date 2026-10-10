<div align="center">

# Nodexa

### Game Server Cloud & Hosting Management Platform

A modern, self-hosted platform for managing game servers, customers, billing, support and infrastructure from one unified interface.

![Version](https://img.shields.io/badge/version-1.4.1-7657ff?style=for-the-badge)
![Platform](https://img.shields.io/badge/platform-Linux-20232a?style=for-the-badge&logo=linux&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?style=for-the-badge&logo=php&logoColor=white)
![Docker](https://img.shields.io/badge/containers-Docker-2496ed?style=for-the-badge&logo=docker&logoColor=white)

</div>

---

## About Nodexa

Nodexa is an all-in-one game server hosting and infrastructure platform designed for hosting providers, communities and private infrastructure.

It combines server management, customer self-service, billing, support, automation and infrastructure monitoring in one branded platform.

The goal is simple: give administrators and customers everything they need without having to jump between multiple systems.

## Highlights

### Server Management

- Real-time server console
- Start, stop, restart and kill controls
- File manager and SFTP access
- Database management
- Scheduled tasks
- Backups and automated backup policies
- Network and allocation management
- Startup variables
- Server activity logs
- User and subuser permissions
- Live resource statistics for CPU, memory, disk and network

### Client Area

- Customer dashboard
- Server overview
- Billing and invoices
- Order history
- Services Hub
- Support tickets
- Account and profile management
- Notifications
- Automatic backup configuration
- Service add-ons
- Teams and organisations
- Affiliate/referral system
- API tokens and webhooks

### Storefront & Billing

- Hosting product catalogue
- Customer checkout flow
- Automatic server provisioning
- Recurring subscriptions
- Invoice generation
- Payment reminders
- Overdue invoice handling
- Automatic service suspension
- Automatic service restoration after payment
- Resource add-ons for RAM, CPU, disk and backups
- Affiliate commissions

### Smart Provisioning

Nodexa can automatically select an available node based on resource usage and available allocations when a new server is provisioned.

This helps distribute workloads across infrastructure instead of placing every new service on the first available node.

### Operations Center

The Operations Center gives administrators a central view of platform health and automation.

It includes:

- Node health monitoring
- Service status components
- Incident management
- Billing overview
- Subscription management
- Service add-ons
- Affiliate management
- Organisation overview
- Webhook monitoring
- Audit logs
- Manual automation runs

### Public Status Center

Nodexa includes a public status page for platform transparency.

Features include:

- Service status components
- Node health
- Active incidents
- Incident timelines
- Previous incidents
- Global incident banner
- Operational, degraded, maintenance and outage states

### Knowledgebase

A built-in hosting knowledgebase makes it possible to create professional support articles and guides.

Articles support:

- Categories
- Search
- Rich formatting
- Headings and sections
- Numbered and bullet lists
- Code blocks
- Screenshots
- Links
- Information and warning callouts
- Related articles
- Featured articles
- View counters
- Helpful / not helpful voting
- Revision history

### Countdown & Maintenance

Administrators can place the public website into Countdown or Maintenance mode directly from the Admin Area.

Countdown supports:

- Configurable end date and time
- Automatic deactivation at zero
- Automatic page reload when finished
- Animated removal of days, hours and minutes as they reach zero
- Administrator bypass permissions

Maintenance mode supports permission-based administrator bypass so authorised staff can continue working while visitors see the maintenance page.

### Roles & Permissions

Nodexa includes a custom administrator role system with granular permissions for areas such as:

- Users
- Servers
- Nodes
- Storefront & Billing
- Support Tickets
- Knowledgebase
- Operations Center
- Error Center
- Countdown & Maintenance
- Update Center

### Developer Tools

Nodexa includes tools for external integrations and automation.

- Scoped customer API tokens
- Signed webhooks
- Webhook test delivery
- API endpoints for customer profile, servers and invoices
- Audit logging
- Event-driven notifications

## Automation

Nodexa includes scheduled automation for infrastructure and customer services.

The automation system can handle:

- Node health checks
- Automated backups
- Recurring invoice creation
- Payment reminders
- Overdue invoice handling
- Service suspension
- Subscription synchronization

Run manually from the Nodexa application directory:

```bash
php artisan nodexa:automation
```

## Installation

The Nodexa installer provides an interactive installation and management menu.

Run as root:

```bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash
```

The installer can be used to install, update and repair Nodexa and its supporting services.

## Updating

Update an existing Nodexa installation with:

```bash
curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh | sudo bash -s -- update
```

The updater creates a local backup before updating, installs dependencies, rebuilds the frontend, runs database migrations and refreshes application caches.

## Main Areas

| Area | Route |
| --- | --- |
| Storefront | `/` |
| Client Area | `/client` |
| Services Hub | `/client/hub` |
| Server Control | `/server/{identifier}` |
| Knowledgebase | `/knowledgebase` |
| Public Status | `/status` |
| Admin Area | `/admin` |
| Operations Center | `/admin/operations` |

## Technology

Nodexa uses a modern web stack built around:

- PHP
- Laravel
- React
- TypeScript
- Docker
- Redis
- MySQL / MariaDB
- Nginx
- Node.js tooling for frontend builds

## Security

Nodexa includes multiple security-focused features:

- Role-based access control
- Two-factor authentication support
- Session protection
- CSRF protection
- Admin audit logs
- Scoped API tokens
- Hashed API token storage
- Signed webhooks
- Isolated server containers
- Security activity tracking

## Project Structure

```text
app/                    Backend application code
resources/              Frontend, views and assets
routes/                 Web and API routes
database/migrations/    Database schema changes
installers/             Installation and repair modules
bin/                    Nodexa command helpers
public/                 Public web assets
```

## Version

Current release: **Nodexa 1.4.1**

The installed version can be checked with:

```bash
cat NODEXA_VERSION
```

## Contributing

Improvements, bug fixes and feature contributions are welcome through GitHub issues and pull requests.

When contributing, please keep changes consistent with the Nodexa design system and existing role/permission structure.

## License

See [`LICENSE.md`](./LICENSE.md) for licensing information.

---

<div align="center">

**Nodexa — Game Server Cloud**

Built for modern hosting infrastructure.

</div>
