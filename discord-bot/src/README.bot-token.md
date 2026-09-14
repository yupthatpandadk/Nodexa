# Bot token

The Discord bot token is never committed or stored in Nodexa's moderation module JSON. It is read from `DISCORD_TOKEN` at process startup. If a token is ever exposed in logs, screenshots or Git history, rotate it in Discord immediately rather than merely deleting the visible copy.
