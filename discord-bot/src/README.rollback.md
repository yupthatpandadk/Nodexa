# Rollback

Because the runtime is isolated as its own service, rollback is straightforward: stop/disable `nodexa-discord`, deploy the previous Nodexa revision, and preserve the `discord-bot/data` directory until moderation history has been reviewed. Do not delete case/audit data as part of an application rollback unless retention policy explicitly requires it.
