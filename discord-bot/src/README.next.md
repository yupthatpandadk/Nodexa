# Final verification before merge

The remaining gate is live Discord verification: supply runtime credentials in the deployment environment, enable privileged intents, install/start the service in a test guild, and verify the command/AutoMod/anti-raid/appeal flows against real Discord permissions. These steps cannot be safely simulated by repository-only changes because they depend on the actual Discord application, guild and role hierarchy.
