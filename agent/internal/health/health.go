package health

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"runtime"
	"strconv"
	"strings"
	"syscall"
	"time"
)

var startedAt = time.Now().UTC()

type SystemMetrics struct {
	MemoryTotalBytes     uint64  `json:"memory_total_bytes"`
	MemoryAvailableBytes uint64  `json:"memory_available_bytes"`
	MemoryUsedBytes      uint64  `json:"memory_used_bytes"`
	DiskTotalBytes       uint64  `json:"disk_total_bytes"`
	DiskFreeBytes        uint64  `json:"disk_free_bytes"`
	DiskUsedBytes        uint64  `json:"disk_used_bytes"`
	Load1                float64 `json:"load_1"`
	Load5                float64 `json:"load_5"`
	Load15               float64 `json:"load_15"`
	CPUCount             int     `json:"cpu_count"`
	UptimeSeconds        uint64  `json:"uptime_seconds"`
}

type Payload struct {
	OK         bool          `json:"ok"`
	Service    string        `json:"service"`
	APIVersion int           `json:"api_version"`
	Version    string        `json:"version"`
	Hostname   string        `json:"hostname"`
	StartedAt  time.Time     `json:"started_at"`
	CheckedAt  time.Time     `json:"checked_at"`
	System     SystemMetrics `json:"system"`
	Warnings   []string      `json:"warnings,omitempty"`
}

func Handler(dataRoot string) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		payload := Collect(dataRoot)
		w.Header().Set("Content-Type", "application/json")
		w.Header().Set("Cache-Control", "no-store")
		w.WriteHeader(http.StatusOK)
		_ = json.NewEncoder(w).Encode(payload)
	}
}

func Collect(dataRoot string) Payload {
	warnings := make([]string, 0, 3)
	memoryTotal, memoryAvailable, err := memory()
	if err != nil {
		warnings = append(warnings, "memory: "+err.Error())
	}

	diskTotal, diskFree, err := disk(dataRoot)
	if err != nil {
		warnings = append(warnings, "disk: "+err.Error())
	}

	load1, load5, load15, err := loadAverage()
	if err != nil {
		warnings = append(warnings, "load: "+err.Error())
	}

	uptime, err := uptimeSeconds()
	if err != nil {
		warnings = append(warnings, "uptime: "+err.Error())
	}

	hostname, err := os.Hostname()
	if err != nil || strings.TrimSpace(hostname) == "" {
		hostname = "unknown"
	}

	return Payload{
		OK:         true,
		Service:    "nodexa-agent",
		APIVersion: 1,
		Version:    version(),
		Hostname:   hostname,
		StartedAt:  startedAt,
		CheckedAt:  time.Now().UTC(),
		System: SystemMetrics{
			MemoryTotalBytes:     memoryTotal,
			MemoryAvailableBytes: memoryAvailable,
			MemoryUsedBytes:      subtractFloor(memoryTotal, memoryAvailable),
			DiskTotalBytes:       diskTotal,
			DiskFreeBytes:        diskFree,
			DiskUsedBytes:        subtractFloor(diskTotal, diskFree),
			Load1:                load1,
			Load5:                load5,
			Load15:               load15,
			CPUCount:             runtime.NumCPU(),
			UptimeSeconds:        uptime,
		},
		Warnings: warnings,
	}
}

func version() string {
	if value := strings.TrimSpace(os.Getenv("NODEXA_AGENT_VERSION")); value != "" {
		return value
	}
	for _, path := range []string{"/var/www/nodexa/agent/VERSION", "/var/lib/nodexa/agent-version", "VERSION"} {
		if body, err := os.ReadFile(path); err == nil {
			if value := strings.TrimSpace(string(body)); value != "" {
				return value
			}
		}
	}
	return "unknown"
}

func memory() (uint64, uint64, error) {
	file, err := os.Open("/proc/meminfo")
	if err != nil {
		return 0, 0, err
	}
	defer file.Close()

	var total, available uint64
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		fields := strings.Fields(scanner.Text())
		if len(fields) < 2 {
			continue
		}
		value, parseErr := strconv.ParseUint(fields[1], 10, 64)
		if parseErr != nil {
			continue
		}
		switch strings.TrimSuffix(fields[0], ":") {
		case "MemTotal":
			total = value * 1024
		case "MemAvailable":
			available = value * 1024
		}
	}
	if err := scanner.Err(); err != nil {
		return 0, 0, err
	}
	if total == 0 {
		return 0, 0, fmt.Errorf("MemTotal missing from /proc/meminfo")
	}
	return total, available, nil
}

func disk(path string) (uint64, uint64, error) {
	if strings.TrimSpace(path) == "" {
		path = "/"
	}
	if _, err := os.Stat(path); err != nil {
		path = "/"
	}
	var stat syscall.Statfs_t
	if err := syscall.Statfs(path, &stat); err != nil {
		return 0, 0, err
	}
	blockSize := uint64(stat.Bsize)
	return stat.Blocks * blockSize, stat.Bavail * blockSize, nil
}

func loadAverage() (float64, float64, float64, error) {
	body, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return 0, 0, 0, err
	}
	fields := strings.Fields(string(body))
	if len(fields) < 3 {
		return 0, 0, 0, fmt.Errorf("invalid /proc/loadavg")
	}
	one, err1 := strconv.ParseFloat(fields[0], 64)
	five, err5 := strconv.ParseFloat(fields[1], 64)
	fifteen, err15 := strconv.ParseFloat(fields[2], 64)
	if err1 != nil || err5 != nil || err15 != nil {
		return 0, 0, 0, fmt.Errorf("invalid load values")
	}
	return one, five, fifteen, nil
}

func uptimeSeconds() (uint64, error) {
	body, err := os.ReadFile("/proc/uptime")
	if err != nil {
		return 0, err
	}
	fields := strings.Fields(string(body))
	if len(fields) == 0 {
		return 0, fmt.Errorf("invalid /proc/uptime")
	}
	seconds, err := strconv.ParseFloat(fields[0], 64)
	if err != nil {
		return 0, err
	}
	if seconds < 0 {
		seconds = 0
	}
	return uint64(seconds), nil
}

func subtractFloor(total, free uint64) uint64 {
	if free >= total {
		return 0
	}
	return total - free
}
