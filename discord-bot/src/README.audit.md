# Audit trail

The audit foundation uses append-only NDJSON to make staff/system events easy to inspect and migrate. Audit data should record identifiers and actions rather than secrets. Production deployments should apply filesystem permissions and retention/backup policy to the audit file.
