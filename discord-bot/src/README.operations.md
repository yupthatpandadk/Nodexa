# Operations

Service: `nodexa-discord.service`

Useful commands:

```bash
systemctl status nodexa-discord
systemctl restart nodexa-discord
journalctl -u nodexa-discord -n 100 --no-pager
```

Case/warning state defaults to `discord-bot/data/moderation.json`. Appeals default to `discord-bot/data/appeals.json`. Audit events default to `discord-bot/data/audit.ndjson`. Back up the data directory and restrict filesystem permissions because moderation history can contain user IDs and staff actions.
