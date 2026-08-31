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
	"github.com/docker/docker/client"
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
	c, e := client.NewClientWithOpts(client.FromEnv, client.WithAPIVersionNegotiation())
	if e != nil {
		return nil, e
	}
	if err := os.MkdirAll(dataRoot, 0750); err != nil {
		return nil, err
	}
	return &Manager{c, dataRoot}, nil
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

func (m *Manager) Create(ctx context.Context, r server.CreateRequest) error {
	root, err := m.serverRoot(r.ID)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(root, 0750); err != nil {
		return err
	}
	mem := r.MemoryMB * 1024 * 1024
	cfg := &container.Config{Image: r.Image, Cmd: []string{"/bin/sh", "-lc", r.Startup}, Env: env(r.Environment), Tty: true, OpenStdin: true, AttachStdin: true, AttachStdout: true, AttachStderr: true, Labels: map[string]string{"nodexa.server": r.ID}}
	host := &container.HostConfig{Resources: container.Resources{Memory: mem, NanoCPUs: r.CPULimit * 10_000_000}, Binds: []string{fmt.Sprintf("%s:/home/container", root)}}
	_, err = m.cli.ContainerCreate(ctx, cfg, host, nil, nil, "nx-"+r.ID)
	return err
}
func env(mv map[string]string) []string {
	o := make([]string, 0, len(mv))
	for k, v := range mv {
		o = append(o, k+"="+v)
	}
	return o
}
func (m *Manager) Start(ctx context.Context, id string) error {
	return m.cli.ContainerStart(ctx, "nx-"+id, container.StartOptions{})
}
func (m *Manager) Stop(ctx context.Context, id string) error {
	t := 10
	return m.cli.ContainerStop(ctx, "nx-"+id, container.StopOptions{Timeout: &t})
}
func (m *Manager) Kill(ctx context.Context, id string) error {
	return m.cli.ContainerKill(ctx, "nx-"+id, "SIGKILL")
}
func (m *Manager) Restart(ctx context.Context, id string) error {
	t := 10
	return m.cli.ContainerRestart(ctx, "nx-"+id, container.StopOptions{Timeout: &t})
}

func (m *Manager) Command(ctx context.Context, id, command string) error {
	if strings.TrimSpace(command) == "" {
		return errors.New("empty command")
	}
	exec, err := m.cli.ContainerExecCreate(ctx, "nx-"+id, container.ExecOptions{AttachStdout: true, AttachStderr: true, Cmd: []string{"/bin/sh", "-lc", command}})
	if err != nil {
		return err
	}
	return m.cli.ContainerExecStart(ctx, exec.ID, container.ExecStartOptions{})
}

func (m *Manager) Logs(ctx context.Context, id string, tail string) (io.ReadCloser, error) {
	if tail == "" {
		tail = "200"
	}
	return m.cli.ContainerLogs(ctx, "nx-"+id, container.LogsOptions{ShowStdout: true, ShowStderr: true, Timestamps: true, Tail: tail})
}

func (m *Manager) Stats(ctx context.Context, id string) (Stats, error) {
	inspect, err := m.cli.ContainerInspect(ctx, "nx-"+id)
	if err != nil {
		return Stats{}, err
	}
	out := Stats{State: inspect.State.Status}
	if !inspect.State.Running {
		return out, nil
	}
	r, err := m.cli.ContainerStatsOneShot(ctx, "nx-"+id)
	if err != nil {
		return out, err
	}
	defer r.Body.Close()
	var s container.StatsResponse
	if err := json.NewDecoder(r.Body).Decode(&s); err != nil {
		return out, err
	}
	cpuDelta := float64(s.CPUStats.CPUUsage.TotalUsage - s.PreCPUStats.CPUUsage.TotalUsage)
	sysDelta := float64(s.CPUStats.SystemUsage - s.PreCPUStats.SystemUsage)
	cpus := float64(s.CPUStats.OnlineCPUs)
	if cpus == 0 {
		cpus = float64(len(s.CPUStats.CPUUsage.PercpuUsage))
	}
	if cpus == 0 {
		cpus = 1
	}
	if sysDelta > 0 && cpuDelta >= 0 {
		out.CPUPercent = (cpuDelta / sysDelta) * cpus * 100
	}
	out.MemoryBytes = s.MemoryStats.Usage
	out.MemoryLimit = s.MemoryStats.Limit
	for _, n := range s.Networks {
		out.NetworkRxBytes += n.RxBytes
		out.NetworkTxBytes += n.TxBytes
	}
	return out, nil
}

func (m *Manager) Backup(id, name string) (string, error) {
	root, err := m.serverRoot(id)
	if err != nil {
		return "", err
	}
	backupDir := filepath.Join(m.dataRoot, ".backups", id)
	if err := os.MkdirAll(backupDir, 0750); err != nil {
		return "", err
	}
	safe := strings.Map(func(r rune) rune {
		if r >= 'a' && r <= 'z' || r >= 'A' && r <= 'Z' || r >= '0' && r <= '9' || r == '-' || r == '_' {
			return r
		}
		return '-'
	}, name)
	if safe == "" {
		safe = time.Now().UTC().Format("20060102-150405")
	}
	dest := filepath.Join(backupDir, safe+".tar.gz")
	f, err := os.Create(dest)
	if err != nil {
		return "", err
	}
	defer f.Close()
	gz := gzip.NewWriter(f)
	defer gz.Close()
	tw := tar.NewWriter(gz)
	defer tw.Close()
	err = filepath.Walk(root, func(p string, info os.FileInfo, e error) error {
		if e != nil {
			return e
		}
		rel, e := filepath.Rel(root, p)
		if e != nil {
			return e
		}
		h, e := tar.FileInfoHeader(info, "")
		if e != nil {
			return e
		}
		h.Name = filepath.ToSlash(rel)
		if e = tw.WriteHeader(h); e != nil {
			return e
		}
		if info.Mode().IsRegular() {
			rf, e := os.Open(p)
			if e != nil {
				return e
			}
			_, copyErr := io.Copy(tw, rf)
			closeErr := rf.Close()
			if copyErr != nil {
				return copyErr
			}
			return closeErr
		}
		return nil
	})
	return dest, err
}
