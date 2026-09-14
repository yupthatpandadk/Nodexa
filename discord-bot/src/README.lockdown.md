# Lockdown safety

Auto Lockdown changes `SendMessages` on text channels for the guild's everyone role. Because existing channel overrides can be complex, production validation must confirm restoration behavior on the actual guild structure. Keep a manual `/unlock` recovery path and test before enabling automatic lockdown.
