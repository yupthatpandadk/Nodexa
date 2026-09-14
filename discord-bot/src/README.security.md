# Moderation safety invariants

The runtime must never moderate the guild owner, must respect Discord role hierarchy, must not expose tokens, and should keep destructive automatic actions behind explicit Nodexa configuration. Staff exemptions and channel/user exemptions are evaluated before AutoMod. Production rollout should begin with logging-only or conservative thresholds in a test guild.
