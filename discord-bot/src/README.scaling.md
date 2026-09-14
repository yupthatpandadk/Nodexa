# Scaling

The initial runtime uses local JSON/NDJSON state so it can be deployed beside a single Nodexa panel without new infrastructure. For multiple runtime replicas or multiple panel nodes, replace the store adapters with MySQL/Redis-backed implementations before horizontal scaling to avoid split case sequences and per-process raid/spam windows.
