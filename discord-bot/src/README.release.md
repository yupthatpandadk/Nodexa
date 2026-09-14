# Release gate

This runtime branch should only be merged to `main` and included in a Nodexa VERSION/CHANGELOG release after CI passes and a live Discord test guild verifies gateway intents, role hierarchy, commands, logs, AutoMod, warning escalation, exemptions, anti-raid lockdown/restoration and appeals. This avoids publishing an Update Center release that advertises moderation features before the Discord-side behavior is verified.
