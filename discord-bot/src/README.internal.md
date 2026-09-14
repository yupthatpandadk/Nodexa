# Runtime architecture

`index.js` owns Discord gateway events and commands. `store.js` persists cases/warnings. `policy.js` handles exemptions, staff access and warning escalation. `raid.js` owns join-rate detection and lockdown. `appeals.js` provides persistent appeal records for the upcoming interaction/UI flow. `audit.js` provides append-only event storage.

The Laravel admin panel remains the source of configuration. Runtime state is deliberately separate from panel configuration so case history is never overwritten when admins save module settings.
