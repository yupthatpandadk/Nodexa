# Runtime security

Never commit the Discord bot token. Use environment variables or the deployment secret store. Grant the bot only the Discord permissions required by enabled moderation features. Keep the bot role below owner/admin roles and above roles it is expected to moderate. Message Content and Guild Members privileged intents are required for the current AutoMod and anti-raid foundation.
