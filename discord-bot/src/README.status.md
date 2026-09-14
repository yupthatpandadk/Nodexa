# Status

Repository implementation is now feature-complete enough for Discord-side integration testing. It is intentionally on `feature/moderation-runtime`, not `main`, and Nodexa stable VERSION has not been bumped. Promotion requires CI plus a real test-guild run because Discord credentials, privileged intents and role hierarchy are external runtime dependencies.
