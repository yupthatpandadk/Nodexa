package docker

import (
    "context"
    "io"

    "github.com/docker/docker/api/types/container"
)

// StreamLogs follows stdout/stderr from the server's primary process. The
// caller owns the returned reader and must close it when the client leaves.
func (m *Manager) StreamLogs(ctx context.Context, id string, tail string) (io.ReadCloser, error) {
    if tail == "" { tail = "100" }
    return m.cli.ContainerLogs(ctx, "nx-"+id, container.LogsOptions{
        ShowStdout: true,
        ShowStderr: true,
        Timestamps: true,
        Follow: true,
        Tail: tail,
    })
}
