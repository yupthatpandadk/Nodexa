# Production readiness

Before merging/releasing:

- [ ] CI is green on the runtime branch/PR.
- [ ] Discord token/client/guild IDs are configured only as secrets/environment variables.
- [ ] Privileged intents are enabled in Discord Developer Portal.
- [ ] Bot role hierarchy is verified in a test guild.
- [ ] Nodexa config path points to the active panel installation.
- [ ] AutoMod filters are tested with exempt staff/channel cases.
- [ ] Warn escalation is tested without affecting real members.
- [ ] Anti-raid threshold and lockdown are tested in a test guild.
- [ ] Appeal submission/review interaction is verified.
- [ ] Case/audit data directory is writable and backed up.

Do not bump Nodexa stable VERSION until these checks pass.
