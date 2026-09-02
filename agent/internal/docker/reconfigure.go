package docker

import (
    "context"
    "fmt"

    "github.com/docker/docker/api/types/container"
    "github.com/docker/docker/errdefs"
    "nodexa/agent/internal/server"
)

// Reconfigure recreates only the Docker runtime container. Server data remains
// untouched in the persistent host directory. If the server was running before
// the change, it is started again after the new runtime has been created.
func (m *Manager) Reconfigure(ctx context.Context, r server.CreateRequest) error {
    root, err := m.serverRoot(r.ID)
    if err != nil { return err }
    if err := m.ensureImage(ctx, r.Image); err != nil { return err }

    name := "nx-" + r.ID
    wasRunning := false
    inspect, err := m.cli.ContainerInspect(ctx, name)
    if err == nil {
        wasRunning = inspect.State != nil && inspect.State.Running
        if wasRunning {
            timeout := 10
            if err := m.cli.ContainerStop(ctx, name, container.StopOptions{Timeout: &timeout}); err != nil {
                return fmt.Errorf("stop server before runtime reconfiguration: %w", err)
            }
        }
        if err := m.cli.ContainerRemove(ctx, name, container.RemoveOptions{Force: true}); err != nil {
            return fmt.Errorf("remove old runtime container: %w", err)
        }
    } else if !errdefs.IsNotFound(err) {
        return fmt.Errorf("inspect runtime container: %w", err)
    }

    if err := m.createContainer(ctx, r, root); err != nil {
        return fmt.Errorf("create reconfigured runtime container: %w", err)
    }
    if wasRunning {
        if err := m.StartWithDiagnostics(ctx, r.ID); err != nil {
            return fmt.Errorf("runtime was reconfigured but could not be restarted: %w", err)
        }
    }
    return nil
}
