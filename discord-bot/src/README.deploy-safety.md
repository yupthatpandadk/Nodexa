# Safe deployment sequence

Deploy runtime files first with moderation disabled. Configure secrets and intents, start the service, verify logs/commands, then enable individual moderation engines from Nodexa. Enable Auto Lockdown last. This sequencing prevents a fresh deployment from immediately applying untested destructive policies.
