package docker

import (
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/docker/docker/api/types/container"
)

func isManagedMinecraftTemplate(template string) bool {
	template = strings.ToLower(strings.TrimSpace(template))
	return template == "minecraft" || template == "minecraft-java"
}

// validateManagedRuntime prevents a managed Minecraft runtime from booting
// after an interrupted/failed installation. A stale Docker container may still
// exist after an old install failure, but it must never be allowed to start if
// the managed files are incomplete.
func (m *Manager) validateManagedRuntime(id, template string) error {
	if !isManagedMinecraftTemplate(template) {
		return nil
	}
	root, err := m.serverRoot(id)
	if err != nil {
		return err
	}

	marker := filepath.Join(root, ".nodexa-installed")
	if _, err := os.Stat(marker); err != nil {
		if os.IsNotExist(err) {
			return fmt.Errorf("managed Minecraft installation is incomplete: installation marker is missing; run Geninstaller server before starting")
		}
		return fmt.Errorf("inspect Minecraft installation marker: %w", err)
	}

	jarPath := filepath.Join(root, "server.jar")
	info, err := os.Stat(jarPath)
	if err != nil {
		if os.IsNotExist(err) {
			return fmt.Errorf("managed Minecraft installation is incomplete: server.jar is missing; run Geninstaller server before starting")
		}
		return fmt.Errorf("inspect server.jar: %w", err)
	}
	if !info.Mode().IsRegular() || info.Size() < 1024 {
		return fmt.Errorf("managed Minecraft installation is invalid: server.jar is empty or not a regular file; run Geninstaller server again")
	}

	f, err := os.Open(jarPath)
	if err != nil {
		return fmt.Errorf("open server.jar: %w", err)
	}
	defer f.Close()
	header := make([]byte, 2)
	if _, err := io.ReadFull(f, header); err != nil {
		return fmt.Errorf("read server.jar header: %w", err)
	}
	if header[0] != 'P' || header[1] != 'K' {
		return fmt.Errorf("managed Minecraft installation is invalid: server.jar is not a valid JAR/ZIP file; run Geninstaller server again")
	}
	return nil
}

// PrepareRuntimePermissions repairs the ownership of files created by a
// managed-template installer. The installer intentionally runs as root, while
// Pterodactyl-compatible Yolks run the game process as uid/gid 1000. Without
// this hand-off a successful Minecraft install can leave server.jar and the
// server directory inaccessible/writable to the runtime container.
func (m *Manager) PrepareRuntimePermissions(id string) error {
	root, err := m.serverRoot(id)
	if err != nil {
		return err
	}

	// Only touch managed-template servers. Custom server images may use a
	// different uid/gid and must keep their own ownership model.
	if _, err := os.Stat(filepath.Join(root, ".nodexa-installed")); err != nil {
		if os.IsNotExist(err) {
			return nil
		}
		return err
	}

	return filepath.Walk(root, func(path string, info os.FileInfo, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if err := os.Chown(path, 1000, 1000); err != nil {
			return fmt.Errorf("set runtime ownership for %s: %w", filepath.Base(path), err)
		}
		// Keep files private to the server user while ensuring the runtime can
		// traverse directories and update Minecraft-generated files.
		if info.IsDir() {
			if err := os.Chmod(path, 0750); err != nil {
				return err
			}
		}
		return nil
	})
}

func (m *Manager) immediateExitLogs(ctx context.Context, id string) string {
	reader, err := m.cli.ContainerLogs(ctx, "nx-"+id, container.LogsOptions{
		ShowStdout: true,
		ShowStderr: true,
		Timestamps: false,
		Tail:       "80",
	})
	if err != nil {
		return ""
	}
	defer reader.Close()
	body, err := io.ReadAll(io.LimitReader(reader, 256*1024))
	if err != nil {
		return ""
	}
	return strings.TrimSpace(string(body))
}

// StartWithDiagnostics validates and repairs managed-template files before boot
// and verifies that the game process did not immediately crash. This turns a
// silent Start-button failure into the actual installation/Docker/game error.
func (m *Manager) StartWithDiagnostics(ctx context.Context, id string) error {
	before, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil {
		return fmt.Errorf("inspect server before start: %w", err)
	}
	template := ""
	if before.Config != nil && before.Config.Labels != nil {
		template = before.Config.Labels["nodexa.template"]
	}
	if err := m.validateManagedRuntime(id, template); err != nil {
		return err
	}
	if err := m.PrepareRuntimePermissions(id); err != nil {
		return fmt.Errorf("prepare server files: %w", err)
	}
	if err := m.Start(ctx, id); err != nil {
		return fmt.Errorf("start server container: %w", err)
	}

	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-time.After(1200 * time.Millisecond):
	}

	inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil {
		return fmt.Errorf("inspect server after start: %w", err)
	}
	if inspect.State.Running {
		return nil
	}

	logs := m.immediateExitLogs(ctx, id)
	if logs == "" {
		return fmt.Errorf("server exited immediately after start (state=%s, exit_code=%d)", inspect.State.Status, inspect.State.ExitCode)
	}
	return fmt.Errorf("server exited immediately after start (exit_code=%d): %s", inspect.State.ExitCode, logs)
}
