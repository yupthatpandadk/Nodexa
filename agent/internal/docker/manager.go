package docker

import (
	"archive/tar"
	"compress/gzip"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/image"
	"github.com/docker/docker/client"
	"github.com/docker/docker/errdefs"
	"nodexa/agent/internal/server"
)

type Manager struct {
	cli      *client.Client
	dataRoot string
}

type Stats struct {
	State          string  `json:"state"`
	CPUPercent     float64 `json:"cpu_percent"`
	MemoryBytes    uint64  `json:"memory_bytes"`
	MemoryLimit    uint64  `json:"memory_limit"`
	NetworkRxBytes uint64  `json:"network_rx_bytes"`
	NetworkTxBytes uint64  `json:"network_tx_bytes"`
}

func New(dataRoot string) (*Manager, error) {
	c, err := client.NewClientWithOpts(client.FromEnv, client.WithAPIVersionNegotiation())
	if err != nil {
		return nil, err
	}
	if err := os.MkdirAll(dataRoot, 0750); err != nil {
		return nil, err
	}
	return &Manager{cli: c, dataRoot: dataRoot}, nil
}

func (m *Manager) serverRoot(id string) (string, error) {
	if id == "" || strings.ContainsAny(id, "/\\") {
		return "", errors.New("invalid server id")
	}
	return filepath.Join(m.dataRoot, id), nil
}

func (m *Manager) SafePath(id, relative string) (string, error) {
	root, err := m.serverRoot(id)
	if err != nil {
		return "", err
	}
	clean := filepath.Clean("/" + relative)
	target := filepath.Join(root, clean)
	rel, err := filepath.Rel(root, target)
	if err != nil {
		return "", err
	}
	if rel == ".." || strings.HasPrefix(rel, ".."+string(os.PathSeparator)) {
		return "", errors.New("path escapes server root")
	}
	return target, nil
}

func (m *Manager) ensureImage(ctx context.Context, ref string) error {
	if strings.TrimSpace(ref) == "" {
		return errors.New("docker image is required")
	}
	if _, _, err := m.cli.ImageInspectWithRaw(ctx, ref); err == nil {
		return nil
	} else if !errdefs.IsNotFound(err) {
		return fmt.Errorf("inspect docker image %q: %w", ref, err)
	}
	reader, err := m.cli.ImagePull(ctx, ref, image.PullOptions{})
	if err != nil {
		return fmt.Errorf("pull docker image %q: %w", ref, err)
	}
	defer reader.Close()
	if _, err := io.Copy(io.Discard, reader); err != nil {
		return fmt.Errorf("download docker image %q: %w", ref, err)
	}
	return nil
}

func (m *Manager) installTemplate(ctx context.Context, r server.CreateRequest, force bool) error {
	template := strings.ToLower(strings.TrimSpace(r.Template))
	if template == "" || template == "custom" || template == "fivem" {
		return nil
	}
	if template != "minecraft" && template != "minecraft-java" {
		return fmt.Errorf("unsupported installation template %q", r.Template)
	}

	root, err := m.serverRoot(r.ID)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(root, 0750); err != nil {
		return err
	}
	marker := filepath.Join(root, ".nodexa-installed")
	if !force {
		if _, err := os.Stat(marker); err == nil {
			return nil
		}
	}

	// Nodexa's built-in Minecraft template behaves like an Egg installer: it
	// restores the runtime-owned files while preserving worlds, plugins and
	// customer configuration. server.properties is only created when missing.
	installScript := `set -eu
cd /mnt/server
VERSION="${MINECRAFT_VERSION:-1.21.8}"
echo "[Nodexa] Installing Paper Minecraft ${VERSION}..."
META="$(curl -fsSL "https://api.papermc.io/v2/projects/paper/versions/${VERSION}/builds")"
BUILD="$(printf '%s' "$META" | sed -n 's/.*"builds"[[:space:]]*:[[:space:]]*\[\([^]]*\)\].*/\1/p' | tr ',' '\n' | tail -n1 | tr -dc '0-9')"
if [ -z "$BUILD" ]; then echo "No Paper build found for Minecraft ${VERSION}" >&2; exit 42; fi
URL="https://api.papermc.io/v2/projects/paper/versions/${VERSION}/builds/${BUILD}/downloads/paper-${VERSION}-${BUILD}.jar"
curl -fL "$URL" -o server.jar.nodexa
mv -f server.jar.nodexa server.jar
printf 'eula=true\n' > eula.txt
if [ ! -f server.properties ]; then
  PORT="${SERVER_PORT:-25565}"
  printf 'server-port=%s\nmotd=A Nodexa Minecraft Server\nenable-query=true\nquery.port=%s\n' "$PORT" "$PORT" > server.properties
fi
printf 'template=minecraft-java\nversion=%s\nbuild=%s\ninstalled_at=%s\n' "$VERSION" "$BUILD" "$(date -u +%FT%TZ)" > .nodexa-installed
echo "[Nodexa] Minecraft files installed successfully."`

	name := "nx-install-" + r.ID
	_ = m.cli.ContainerRemove(ctx, name, container.RemoveOptions{Force: true})
	cfg := &container.Config{
		Image:      r.Image,
		Cmd:        []string{"/bin/sh", "-lc", installScript},
		Env:        env(r.Environment),
		WorkingDir: "/mnt/server",
	}
	host := &container.HostConfig{Binds: []string{fmt.Sprintf("%s:/mnt/server", root)}}
	created, err := m.cli.ContainerCreate(ctx, cfg, host, nil, nil, name)
	if err != nil {
		return fmt.Errorf("create template installer: %w", err)
	}
	defer m.cli.ContainerRemove(context.Background(), name, container.RemoveOptions{Force: true})
	if err := m.cli.ContainerStart(ctx, created.ID, container.StartOptions{}); err != nil {
		return fmt.Errorf("start template installer: %w", err)
	}

	for {
		inspect, err := m.cli.ContainerInspect(ctx, created.ID)
		if err != nil {
			return fmt.Errorf("inspect template installer: %w", err)
		}
		if !inspect.State.Running {
			if inspect.State.ExitCode != 0 {
				logs, _ := m.cli.ContainerLogs(ctx, created.ID, container.LogsOptions{ShowStdout: true, ShowStderr: true, Tail: "40"})
				message := ""
				if logs != nil {
					body, _ := io.ReadAll(io.LimitReader(logs, 8192))
					_ = logs.Close()
					message = strings.TrimSpace(string(body))
				}
				return fmt.Errorf("Minecraft installer exited with code %d%s", inspect.State.ExitCode, func() string { if message != "" { return ": " + message }; return "" }())
			}
			return nil
		}
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(250 * time.Millisecond):
		}
	}
}

func (m *Manager) createContainer(ctx context.Context, r server.CreateRequest, root string) error {
	mem := r.MemoryMB * 1024 * 1024
	cfg := &container.Config{
		Image:        r.Image,
		Cmd:          []string{"/bin/sh", "-lc", r.Startup},
		Env:          env(r.Environment),
		WorkingDir:   "/home/container",
		Tty:          true,
		OpenStdin:    true,
		AttachStdin:  true,
		AttachStdout: true,
		AttachStderr: true,
		Labels: map[string]string{
			"nodexa.server":   r.ID,
			"nodexa.template": r.Template,
		},
	}
	host := &container.HostConfig{
		Resources: container.Resources{Memory: mem, NanoCPUs: r.CPULimit * 10_000_000},
		Binds:     []string{fmt.Sprintf("%s:/home/container", root)},
	}
	if _, err := m.cli.ContainerCreate(ctx, cfg, host, nil, nil, "nx-"+r.ID); err != nil {
		return fmt.Errorf("create docker container: %w", err)
	}
	return nil
}

func (m *Manager) Create(ctx context.Context, r server.CreateRequest) error {
	if r.MemoryMB < 128 {
		return errors.New("memory_mb must be at least 128")
	}
	if r.CPULimit < 0 {
		return errors.New("cpu_limit cannot be negative")
	}
	if strings.TrimSpace(r.Startup) == "" {
		return errors.New("startup command is required")
	}
	root, err := m.serverRoot(r.ID)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(root, 0750); err != nil {
		return err
	}
	if err := m.ensureImage(ctx, r.Image); err != nil {
		return err
	}
	if err := m.installTemplate(ctx, r, false); err != nil {
		return err
	}
	containerName := "nx-" + r.ID
	if _, err := m.cli.ContainerInspect(ctx, containerName); err == nil {
		return nil
	} else if !errdefs.IsNotFound(err) {
		return fmt.Errorf("inspect existing container: %w", err)
	}
	return m.createContainer(ctx, r, root)
}

func (m *Manager) Reinstall(ctx context.Context, r server.CreateRequest) error {
	if strings.TrimSpace(r.Template) == "" || strings.EqualFold(r.Template, "custom") {
		return errors.New("this server has no managed template to reinstall")
	}
	root, err := m.serverRoot(r.ID)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(root, 0750); err != nil {
		return err
	}
	if err := m.ensureImage(ctx, r.Image); err != nil {
		return err
	}
	name := "nx-" + r.ID
	if inspect, err := m.cli.ContainerInspect(ctx, name); err == nil {
		if inspect.State.Running {
			t := 10
			_ = m.cli.ContainerStop(ctx, name, container.StopOptions{Timeout: &t})
		}
		if err := m.cli.ContainerRemove(ctx, name, container.RemoveOptions{Force: true}); err != nil {
			return fmt.Errorf("remove old container before reinstall: %w", err)
		}
	} else if !errdefs.IsNotFound(err) {
		return fmt.Errorf("inspect server before reinstall: %w", err)
	}
	if err := m.installTemplate(ctx, r, true); err != nil {
		return err
	}
	return m.createContainer(ctx, r, root)
}

func env(values map[string]string) []string {
	out := make([]string, 0, len(values))
	for key, value := range values {
		out = append(out, key+"="+value)
	}
	return out
}

func (m *Manager) Start(ctx context.Context, id string) error { return m.cli.ContainerStart(ctx, "nx-"+id, container.StartOptions{}) }
func (m *Manager) Stop(ctx context.Context, id string) error { t := 10; return m.cli.ContainerStop(ctx, "nx-"+id, container.StopOptions{Timeout: &t}) }
func (m *Manager) Kill(ctx context.Context, id string) error { return m.cli.ContainerKill(ctx, "nx-"+id, "SIGKILL") }
func (m *Manager) Restart(ctx context.Context, id string) error { t := 10; return m.cli.ContainerRestart(ctx, "nx-"+id, container.StopOptions{Timeout: &t}) }

func (m *Manager) Command(ctx context.Context, id, command string) error {
	if strings.TrimSpace(command) == "" { return errors.New("empty command") }
	exec, err := m.cli.ContainerExecCreate(ctx, "nx-"+id, container.ExecOptions{AttachStdout: true, AttachStderr: true, Cmd: []string{"/bin/sh", "-lc", command}})
	if err != nil { return err }
	return m.cli.ContainerExecStart(ctx, exec.ID, container.ExecStartOptions{})
}

func (m *Manager) Logs(ctx context.Context, id string, tail string) (io.ReadCloser, error) {
	if tail == "" { tail = "200" }
	return m.cli.ContainerLogs(ctx, "nx-"+id, container.LogsOptions{ShowStdout: true, ShowStderr: true, Timestamps: true, Tail: tail})
}

func (m *Manager) Stats(ctx context.Context, id string) (Stats, error) {
	inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil { return Stats{}, err }
	out := Stats{State: inspect.State.Status}
	if !inspect.State.Running { return out, nil }
	response, err := m.cli.ContainerStatsOneShot(ctx, "nx-"+id)
	if err != nil { return out, err }
	defer response.Body.Close()
	var stats container.StatsResponse
	if err := json.NewDecoder(response.Body).Decode(&stats); err != nil { return out, err }
	var cpuDelta, sysDelta float64
	if stats.CPUStats.CPUUsage.TotalUsage >= stats.PreCPUStats.CPUUsage.TotalUsage { cpuDelta = float64(stats.CPUStats.CPUUsage.TotalUsage - stats.PreCPUStats.CPUUsage.TotalUsage) }
	if stats.CPUStats.SystemUsage >= stats.PreCPUStats.SystemUsage { sysDelta = float64(stats.CPUStats.SystemUsage - stats.PreCPUStats.SystemUsage) }
	cpus := float64(stats.CPUStats.OnlineCPUs)
	if cpus == 0 { cpus = float64(len(stats.CPUStats.CPUUsage.PercpuUsage)) }
	if cpus == 0 { cpus = 1 }
	if sysDelta > 0 { out.CPUPercent = (cpuDelta / sysDelta) * cpus * 100 }
	out.MemoryBytes = stats.MemoryStats.Usage
	out.MemoryLimit = stats.MemoryStats.Limit
	for _, network := range stats.Networks { out.NetworkRxBytes += network.RxBytes; out.NetworkTxBytes += network.TxBytes }
	return out, nil
}

func (m *Manager) Backup(id, name string) (string, error) {
	root, err := m.serverRoot(id)
	if err != nil { return "", err }
	if info, err := os.Stat(root); err != nil { return "", fmt.Errorf("server data directory unavailable: %w", err) } else if !info.IsDir() { return "", errors.New("server data path is not a directory") }
	backupDir := filepath.Join(m.dataRoot, ".backups", id)
	if err := os.MkdirAll(backupDir, 0750); err != nil { return "", err }
	safe := strings.Map(func(r rune) rune { if r >= 'a' && r <= 'z' || r >= 'A' && r <= 'Z' || r >= '0' && r <= '9' || r == '-' || r == '_' { return r }; return '-' }, name)
	if safe == "" { safe = time.Now().UTC().Format("20060102-150405") }
	dest := filepath.Join(backupDir, safe+".tar.gz")
	file, err := os.Create(dest)
	if err != nil { return "", err }
	defer file.Close()
	gzipWriter := gzip.NewWriter(file); defer gzipWriter.Close()
	tarWriter := tar.NewWriter(gzipWriter); defer tarWriter.Close()
	err = filepath.Walk(root, func(path string, info os.FileInfo, walkErr error) error {
		if walkErr != nil { return walkErr }
		rel, err := filepath.Rel(root, path); if err != nil { return err }
		header, err := tar.FileInfoHeader(info, ""); if err != nil { return err }
		header.Name = filepath.ToSlash(rel)
		if err := tarWriter.WriteHeader(header); err != nil { return err }
		if info.Mode().IsRegular() {
			input, err := os.Open(path); if err != nil { return err }
			_, copyErr := io.Copy(tarWriter, input); closeErr := input.Close()
			if copyErr != nil { return copyErr }; return closeErr
		}
		return nil
	})
	return dest, err
}
