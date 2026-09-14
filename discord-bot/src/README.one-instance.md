# Single-instance requirement

Until moderation state and rate windows move to shared MySQL/Redis storage, run one active runtime instance per configured guild. Multiple replicas could otherwise generate conflicting case sequences or independent spam/raid counters.
