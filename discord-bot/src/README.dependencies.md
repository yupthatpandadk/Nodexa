# Dependencies

The runtime intentionally has one production package dependency: `discord.js`. Node built-ins are used for persistence, tests, filesystem access and process management helpers. Keeping the dependency surface small makes deployment and security review simpler.
