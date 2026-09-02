package docker

import (
    "context"
    "io"
    "strings"

    "github.com/docker/docker/api/types/container"
)

// StreamLogs follows stdout/stderr from the current server session.
//
// Docker keeps logs from previous starts in the same container. Returning a
// normal Tail therefore makes an old crash/startup session appear again after
// Start/Restart. Pterodactyl-style consoles instead begin at the current boot.
// We use Docker's StartedAt timestamp as Since whenever the container has been
// started. This keeps historical sessions out while still returning the full
// output produced by the current boot before the browser connected.
func (m *Manager) StreamLogs(ctx context.Context, id string, tail string) (io.ReadCloser, error) {
    if tail == "" {
        tail = "100"
    }

    options := container.LogsOptions{
        ShowStdout: true,
        ShowStderr: true,
        Timestamps: true,
        Follow: true,
        Tail: tail,
    }

    if inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id); err == nil && inspect.State != nil {
        startedAt := strings.TrimSpace(inspect.State.StartedAt)
        if startedAt != "" &&
            startedAt != "0001-01-01T00:00:00Z" &&
            startedAt != "0001-01-01T00:00:00.000000000Z" {
            options.Since = startedAt
            // Since already limits output to this boot. Do not additionally
            // truncate startup output with Tail.
            options.Tail = "all"
        }
    }

    return m.cli.ContainerLogs(ctx, "nx-"+id, options)
}
