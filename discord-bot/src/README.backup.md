# Backups

Back up the `discord-bot/data` directory with the rest of Nodexa application state. The runtime includes a small file backup helper, but scheduling and retention should be handled by the deployment environment. Restore moderation state only while the runtime is stopped to avoid concurrent writes.
