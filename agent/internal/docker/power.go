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

func runtimeReferencesServerJar(cfg *container.Config) bool {
	if cfg == nil { return false }
	parts := make([]string, 0, len(cfg.Entrypoint)+len(cfg.Cmd))
	parts = append(parts, cfg.Entrypoint...)
	parts = append(parts, cfg.Cmd...)
	joined := strings.ToLower(strings.Join(parts, " "))
	return strings.Contains(joined, "server.jar") || strings.Contains(joined, "server_jarfile")
}

func runtimeServerJarName(cfg *container.Config) string {
	if cfg != nil {
		for _, item := range cfg.Env {
			parts := strings.SplitN(item, "=", 2)
			if len(parts) == 2 && strings.EqualFold(strings.TrimSpace(parts[0]), "SERVER_JARFILE") {
				if value := strings.TrimSpace(parts[1]); value != "" { return value }
			}
		}
	}
	return "server.jar"
}

func validateJarFilename(name string) error {
	name = strings.TrimSpace(name)
	if name == "" { return fmt.Errorf("server JAR filename is empty") }
	if filepath.Base(name) != name || strings.ContainsAny(name, `/\\`) {
		return fmt.Errorf("server JAR filename must be a filename, not a path")
	}
	if !strings.HasSuffix(strings.ToLower(name), ".jar") {
		return fmt.Errorf("server JAR filename must end with .jar")
	}
	return nil
}

func (m *Manager) validateServerJarNamed(id, jarName string) error {
	if err := validateJarFilename(jarName); err != nil { return err }
	root, err := m.serverRoot(id)
	if err != nil { return err }
	jarPath := filepath.Join(root, jarName)
	info, err := os.Stat(jarPath)
	if err != nil {
		if os.IsNotExist(err) { return fmt.Errorf("%s is missing; run Geninstaller server before starting", jarName) }
		return fmt.Errorf("inspect %s: %w", jarName, err)
	}
	if !info.Mode().IsRegular() || info.Size() < 1024 {
		return fmt.Errorf("%s is empty or not a regular file; run Geninstaller server again", jarName)
	}
	f, err := os.Open(jarPath)
	if err != nil { return fmt.Errorf("open %s: %w", jarName, err) }
	defer f.Close()
	header := make([]byte, 2)
	if _, err := io.ReadFull(f, header); err != nil { return fmt.Errorf("read %s header: %w", jarName, err) }
	if header[0] != 'P' || header[1] != 'K' {
		return fmt.Errorf("%s is not a valid JAR/ZIP file; run Geninstaller server again", jarName)
	}
	return nil
}

func (m *Manager) validateServerJar(id string) error { return m.validateServerJarNamed(id, "server.jar") }

func (m *Manager) validateManagedRuntimeNamed(id, template, jarName string) error {
	if !isManagedMinecraftTemplate(template) { return nil }
	root, err := m.serverRoot(id)
	if err != nil { return err }
	marker := filepath.Join(root, ".nodexa-installed")
	if _, err := os.Stat(marker); err != nil {
		if os.IsNotExist(err) { return fmt.Errorf("managed Minecraft installation is incomplete: installation marker is missing; run Geninstaller server before starting") }
		return fmt.Errorf("inspect Minecraft installation marker: %w", err)
	}
	if err := m.validateServerJarNamed(id, jarName); err != nil {
		return fmt.Errorf("managed Minecraft installation is incomplete: %w", err)
	}
	return nil
}

func (m *Manager) validateManagedRuntime(id, template string) error {
	return m.validateManagedRuntimeNamed(id, template, "server.jar")
}

func (m *Manager) PrepareRuntimePermissions(id string) error {
	root, err := m.serverRoot(id)
	if err != nil { return err }
	if _, err := os.Stat(filepath.Join(root, ".nodexa-installed")); err != nil {
		if os.IsNotExist(err) { return nil }
		return err
	}
	return filepath.Walk(root, func(path string, info os.FileInfo, walkErr error) error {
		if walkErr != nil { return walkErr }
		if err := os.Chown(path, 1000, 1000); err != nil { return fmt.Errorf("set runtime ownership for %s: %w", filepath.Base(path), err) }
		if info.IsDir() {
			if err := os.Chmod(path, 0750); err != nil { return err }
		}
		return nil
	})
}

func (m *Manager) immediateExitLogs(ctx context.Context, id string) string {
	reader, err := m.cli.ContainerLogs(ctx, "nx-"+id, container.LogsOptions{ShowStdout:true, ShowStderr:true, Timestamps:false, Tail:"80"})
	if err != nil { return "" }
	defer reader.Close()
	body, err := io.ReadAll(io.LimitReader(reader, 256*1024))
	if err != nil { return "" }
	return strings.TrimSpace(string(body))
}

func (m *Manager) StartWithDiagnostics(ctx context.Context, id string) error {
	before, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil { return fmt.Errorf("inspect server before start: %w", err) }
	template := ""
	if before.Config != nil && before.Config.Labels != nil { template = before.Config.Labels["nodexa.template"] }
	jarName := runtimeServerJarName(before.Config)
	if isManagedMinecraftTemplate(template) {
		if err := m.validateManagedRuntimeNamed(id, template, jarName); err != nil { return err }
	} else if runtimeReferencesServerJar(before.Config) {
		if err := m.validateServerJarNamed(id, jarName); err != nil {
			return fmt.Errorf("runtime startup references a server JAR, but the server files are incomplete: %w", err)
		}
	}
	if err := m.PrepareRuntimePermissions(id); err != nil { return fmt.Errorf("prepare server files: %w", err) }
	if err := m.Start(ctx, id); err != nil { return fmt.Errorf("start server container: %w", err) }
	select { case <-ctx.Done(): return ctx.Err(); case <-time.After(1200 * time.Millisecond): }
	inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil { return fmt.Errorf("inspect server after start: %w", err) }
	if inspect.State.Running { return nil }
	logs := m.immediateExitLogs(ctx, id)
	if logs == "" { return fmt.Errorf("server exited immediately after start (state=%s, exit_code=%d)", inspect.State.Status, inspect.State.ExitCode) }
	return fmt.Errorf("server exited immediately after start (exit_code=%d): %s", inspect.State.ExitCode, logs)
}

func (m *Manager) RestartWithDiagnostics(ctx context.Context, id string) error {
	inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil { return fmt.Errorf("inspect server before restart: %w", err) }
	if inspect.State != nil && inspect.State.Running {
		timeout := 10
		if err := m.cli.ContainerStop(ctx, "nx-"+id, container.StopOptions{Timeout:&timeout}); err != nil { return fmt.Errorf("stop server before restart: %w", err) }
	}
	return m.StartWithDiagnostics(ctx, id)
}
