# Container

A minimal Node 20 Alpine Dockerfile is included. Runtime secrets and moderation state are excluded from the build context. If containerized, mount the Nodexa moderation config read-only and the runtime data directory read-write rather than baking either into the image.
