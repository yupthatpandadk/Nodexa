# Service

The included systemd unit starts the runtime from the Nodexa checkout, loads environment variables from `discord-bot/.env`, restarts after failure and runs under `www-data` by default. Adjust the service account/path for the actual deployment and ensure it can read panel config and write only the runtime data directory it needs.
