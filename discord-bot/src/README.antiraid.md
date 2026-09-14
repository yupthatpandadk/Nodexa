# Anti-Raid

The runtime tracks joins per configured time window, logs accounts younger than the configured minimum age and can trigger automatic text-channel lockdown when the join threshold is crossed. The runtime includes a cooldown scheduler foundation for restoring channel permissions. Always verify role/channel exemptions and restoration behavior in a test guild before enabling Auto Lockdown in production.
