package docker

import (
    "context"
    "errors"
    "fmt"
    "strings"

    "github.com/docker/docker/api/types/container"
)

// WriteStdin writes directly to the main process stdin of a running server.
// This matches the behaviour users expect from a game-server console: commands
// are handled by Minecraft/FiveM/etc. rather than executed as Linux commands.
func (m *Manager) WriteStdin(ctx context.Context, id, input string) error {
    input = strings.TrimRight(input, "\r\n")
    if strings.TrimSpace(input) == "" { return errors.New("empty console input") }

    name := "nx-" + id
    inspect, err := m.cli.ContainerInspect(ctx, name)
    if err != nil { return fmt.Errorf("inspect server runtime: %w", err) }
    if inspect.State == nil || !inspect.State.Running { return errors.New("server is not running") }

    attach, err := m.cli.ContainerAttach(ctx, name, container.AttachOptions{
        Stream: true,
        Stdin: true,
        Stdout: false,
        Stderr: false,
    })
    if err != nil { return fmt.Errorf("attach server stdin: %w", err) }
    defer attach.Close()

    if _, err := attach.Conn.Write([]byte(input + "\n")); err != nil {
        return fmt.Errorf("write server stdin: %w", err)
    }
    return nil
}
