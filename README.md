# Nodexa

Nodexa is an open game-server management platform with a Laravel/React panel and a Go-based node agent.

> Development preview: do not replace a production Pterodactyl installation with Nodexa yet.

## One-line installer

Run as root on a fresh Ubuntu server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh)
```

The installer provides an interactive menu for Panel, Agent, combined installation, status, updates and removal.

## Components

- `panel/` — PHP/Laravel API and React/TypeScript interface
- `agent/` — Go service controlling Docker containers
- `installer/` — interactive Linux installer modules
- `deploy/` — combined runtime deployment
- `docs/` — feature roadmap and changelog

## Supported installer targets

Ubuntu/Debian systems with `apt`; Ubuntu 22.04/24.04 is recommended. The Agent supports amd64 and arm64 in the current installer.

## License

Nodexa is an original project and is not affiliated with Pterodactyl.
