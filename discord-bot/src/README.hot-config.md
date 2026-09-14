# Hot configuration

Moderation settings are read from Nodexa's module JSON during message/interaction/member events. This allows most admin changes to take effect without restarting the Discord runtime. Environment-level changes such as token, guild ID or storage paths still require a service restart.
