# Nodexa Discord Runtime

Discord.js runtime for Nodexa Moderation & Safety Center.

## Setup

Requires Node.js 20+ and a Discord application/bot with Server Members Intent and Message Content Intent enabled.

```bash
cd discord-bot
npm install
cp .env.example .env
# Export/load the variables from .env with your process manager.
npm start
```

The runtime reads the same moderation configuration written by the Nodexa Laravel panel. Override `NODEXA_MODULE_CONFIG` when the panel lives elsewhere.

## Implemented foundation

- Guild slash commands: warn, timeout, kick, ban, purge, lock, unlock
- Moderation case identifiers and configurable log channel
- DM notification for moderation actions
- AutoMod: spam/flood, mention spam, CAPS, Discord invites, word blacklist and basic scam/phishing patterns
- Anti-raid account-age signal
- Configuration hot-read from Nodexa module JSON

Persistent warning/case history, full join-rate lockdown, appeals and richer duplicate/link/domain engines should be completed before this branch is promoted as a stable release.
