# Data model

Cases use stable `CASE-000001` style IDs and include guild, target user, moderator, action, reason and timestamp. Warn cases are additionally indexed per target user for escalation. Appeals reference a case ID and carry pending/approved/rejected status plus reviewer metadata. Audit is append-only NDJSON. This file-based store is intended as the first production-capable runtime layer; a future database adapter can preserve the same service API for larger multi-guild deployments.
