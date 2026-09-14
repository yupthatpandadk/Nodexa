# Failure modes

If the configuration file cannot be read, the runtime defaults to disabled moderation rather than guessing policy. Discord permission failures are surfaced as safe staff-facing errors. Case writes use a temporary file and rename. Automatic moderation should fail closed only for the individual action; a failed log send must not crash the process. systemd is configured to restart the runtime after process failure.
