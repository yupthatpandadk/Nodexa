# Configuration bridge

The runtime reads `panel/storage/app/nodexa/discord-bot-modules.json` by default and reloads moderation configuration during events, so most settings saved in the admin panel do not require a bot restart. `NODEXA_MODULE_CONFIG` can override the path. Secrets such as the Discord token are environment-only and are intentionally not read from the admin JSON.
