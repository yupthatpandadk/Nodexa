# Moderation data privacy

Runtime data can contain Discord user IDs, moderator IDs, reasons and timestamps. Limit filesystem access to the service account and administrators, define a retention policy appropriate for the community, and avoid storing message content unless it is necessary for a moderation case. Discord bot tokens must never be stored in moderation history or panel configuration exports.
