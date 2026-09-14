# Production deployment

1. Enable **Server Members Intent** and **Message Content Intent** for the Discord application.
2. Create `discord-bot/.env` from `.env.example` and set bot token, application/client ID and guild ID.
3. Point `NODEXA_MODULE_CONFIG` to the panel's `storage/app/nodexa/discord-bot-modules.json` when the default relative path does not match deployment.
4. Run `npm install` and `npm test` in `discord-bot`.
5. Install the service with `sudo bash install-service.sh /path/to/Nodexa`.
6. Start/restart with `sudo systemctl restart nodexa-discord` and inspect with `journalctl -u nodexa-discord -f`.

Do not expose the bot token through the panel or commit it to Git. Test moderation in a non-production Discord guild before enabling automatic lockdown.
