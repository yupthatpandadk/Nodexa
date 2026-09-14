# Integration test checklist

Use a dedicated Discord test guild before production rollout. Verify slash command registration, bot role hierarchy, Message Content and Guild Members intents, moderation logs, DMs, each AutoMod detector, warn escalation, exemptions, join-rate raid trigger, lockdown and restoration, and appeals. Never run destructive ban/lockdown integration tests in the production community first.
