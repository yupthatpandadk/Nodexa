# Nodexa

Nodexa is an open game-server management platform with a Laravel/React panel and a Nodexa node agent.

> Development preview: validate updates in a staging environment before replacing a production installation.

## One-line installer

Run as root on a fresh Ubuntu server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh)
```

The installer provides an interactive menu for Panel, Agent, combined installation, status, updates and removal.

## Components

- `panel/` — PHP/Laravel API and React/TypeScript interface
- `agent/` — Nodexa node service and compatibility tooling
- `installer/` — interactive Linux installer modules
- `deploy/` — combined runtime deployment
- `docs/` — feature roadmap and changelog

## Supported installer targets

Ubuntu/Debian systems with `apt`; Ubuntu 22.04/24.04 is recommended. The Agent supports amd64 and arm64 in the current installer.

## License

See the repository license and bundled third-party notices for applicable terms.
