package docker

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/errdefs"
	"github.com/docker/docker/pkg/stdcopy"
	"nodexa/agent/internal/server"
)

const installLogName = ".nodexa-install.log"

func appendInstallLog(root, message string) {
	message = strings.TrimRight(message, "\r\n")
	if message == "" {
		return
	}
	file, err := os.OpenFile(filepath.Join(root, installLogName), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0640)
	if err != nil {
		return
	}
	defer file.Close()
	_, _ = fmt.Fprintln(file, message)
}

func resetInstallLog(root string) {
	_ = os.WriteFile(filepath.Join(root, installLogName), []byte{}, 0640)
}

func tailInstallText(text string, tail string) string {
	count, err := strconv.Atoi(tail)
	if err != nil || count <= 0 {
		count = 200
	}
	if count > 2000 {
		count = 2000
	}
	lines := strings.Split(strings.ReplaceAll(text, "\r\n", "\n"), "\n")
	if len(lines) > count {
		lines = lines[len(lines)-count:]
	}
	return strings.TrimSpace(strings.Join(lines, "\n"))
}

func (m *Manager) installerLogs(ctx context.Context, containerName, tail string) (string, error) {
	reader, err := m.cli.ContainerLogs(ctx, containerName, container.LogsOptions{
		ShowStdout: true,
		ShowStderr: true,
		Timestamps: false,
		Tail:       tail,
	})
	if err != nil {
		return "", err
	}
	defer reader.Close()

	var output bytes.Buffer
	if _, err := stdcopy.StdCopy(&output, &output, reader); err != nil {
		return "", err
	}
	return strings.TrimSpace(output.String()), nil
}

// InstallConsole returns the live/recent installer output while a server is
// being provisioned or reinstalled. Once the actual game container is running,
// normal container logs take over automatically.
func (m *Manager) InstallConsole(ctx context.Context, id, tail string) (string, bool, error) {
	root, err := m.serverRoot(id)
	if err != nil {
		return "", false, err
	}

	stored := ""
	if body, readErr := os.ReadFile(filepath.Join(root, installLogName)); readErr == nil {
		stored = strings.TrimSpace(string(body))
	}

	installerName := "nx-install-" + id
	if inspect, inspectErr := m.cli.ContainerInspect(ctx, installerName); inspectErr == nil {
		if inspect.State.Running {
			live, logsErr := m.installerLogs(ctx, installerName, tail)
			if logsErr != nil {
				return "", true, logsErr
			}
			combined := strings.TrimSpace(strings.TrimSpace(stored) + "\n" + strings.TrimSpace(live))
			return tailInstallText(combined, tail), true, nil
		}
	} else if !errdefs.IsNotFound(inspectErr) {
		return "", false, inspectErr
	}

	serverName := "nx-" + id
	if inspect, inspectErr := m.cli.ContainerInspect(ctx, serverName); inspectErr == nil {
		if !inspect.State.Running && stored != "" {
			return tailInstallText(stored, tail), true, nil
		}
		return "", false, nil
	} else if !errdefs.IsNotFound(inspectErr) {
		return "", false, inspectErr
	}

	if stored != "" {
		return tailInstallText(stored, tail), true, nil
	}
	return "", false, nil
}

func installerError(exitCode int, captured string) error {
	detail := tailInstallText(captured, "8")
	if detail == "" {
		return fmt.Errorf("Minecraft installer exited with code %d", exitCode)
	}
	return fmt.Errorf("Minecraft installer exited with code %d: %s", exitCode, detail)
}

func (m *Manager) installTemplateWithProgress(ctx context.Context, r server.CreateRequest, root string) error {
	template := strings.ToLower(strings.TrimSpace(r.Template))
	if template != "minecraft" && template != "minecraft-java" {
		return fmt.Errorf("unsupported installation template %q", r.Template)
	}

	installScript := `set -eu
cd /mnt/server
VERSION="${MINECRAFT_VERSION:-1.21.8}"
PORT="${SERVER_PORT:-25565}"
PAPER_UA="Nodexa/0.13.7 (https://github.com/yupthatpandadk/Nodexa)"
PAPER_API="https://fill.papermc.io/v3/projects/paper/versions/${VERSION}/builds/latest"
echo "container@nodexa~ Server marked as installing..."
echo "[Nodexa Installer] [1/7] Preparing installation directory"
echo "[Nodexa Installer] Template: Minecraft Java / Paper"
echo "[Nodexa Installer] Minecraft version: ${VERSION}"
echo "[Nodexa Installer] [2/7] Resolving latest Paper build from Fill v3"
if ! META="$(curl -fsSL -H "User-Agent: ${PAPER_UA}" -H "Accept: application/json" "${PAPER_API}")"; then
  echo "[Nodexa Installer] ERROR: PaperMC Fill API request failed for Minecraft ${VERSION}" >&2
  exit 44
fi
BUILD="$(printf '%s' "$META" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')"
CHANNEL="$(printf '%s' "$META" | sed -n 's/.*"channel":"\([^"]*\)".*/\1/p')"
URL="$(printf '%s' "$META" | grep -o '"url":"[^"]*"' | head -n1 | cut -d'"' -f4)"
SHA="$(printf '%s' "$META" | grep -o '"sha256":"[^"]*"' | head -n1 | cut -d'"' -f4)"
if [ -z "$BUILD" ] || [ -z "$URL" ]; then
  echo "[Nodexa Installer] ERROR: PaperMC returned no downloadable build for Minecraft ${VERSION}" >&2
  exit 42
fi
echo "[Nodexa Installer] Selected Paper build ${BUILD}${CHANNEL:+ (${CHANNEL})}"
echo "[Nodexa Installer] [3/7] Downloading server.jar"
rm -f server.jar.nodexa
if ! curl -fL --progress-bar -H "User-Agent: ${PAPER_UA}" "$URL" -o server.jar.nodexa; then
  rm -f server.jar.nodexa
  echo "[Nodexa Installer] ERROR: Download of Paper build ${BUILD} failed" >&2
  exit 45
fi
if [ -n "$SHA" ] && command -v sha256sum >/dev/null 2>&1; then
  echo "${SHA}  server.jar.nodexa" | sha256sum -c -
fi
echo "[Nodexa Installer] Download complete: $(wc -c < server.jar.nodexa | tr -d ' ') bytes"
echo "[Nodexa Installer] [4/7] Installing Paper runtime"
mv -f server.jar.nodexa server.jar
echo "[Nodexa Installer] [5/7] Accepting Minecraft EULA"
printf 'eula=true\n' > eula.txt
echo "[Nodexa Installer] [6/7] Checking server.properties"
if [ ! -f server.properties ]; then
  printf 'server-port=%s\nmotd=A Nodexa Minecraft Server\nenable-query=true\nquery.port=%s\n' "$PORT" "$PORT" > server.properties
  echo "[Nodexa Installer] Created server.properties on port ${PORT}"
else
  echo "[Nodexa Installer] Existing server.properties preserved"
fi
printf 'template=minecraft-java\nversion=%s\nbuild=%s\ninstalled_at=%s\n' "$VERSION" "$BUILD" "$(date -u +%FT%TZ)" > .nodexa-installed
echo "[Nodexa Installer] [7/7] Verifying installed files"
test -s server.jar
echo "[Nodexa Installer] Minecraft installation completed successfully"
echo "container@nodexa~ Installation process completed."`

	name := "nx-install-" + r.ID
	_ = m.cli.ContainerRemove(ctx, name, container.RemoveOptions{Force: true})

	// Runtime yolks normally start as their unprivileged container user and may
	// have an image entrypoint tailored for /home/container. An Egg installer,
	// however, writes to a separate /mnt/server bind. Run that one-shot installer
	// explicitly as root and bypass the runtime entrypoint.
	cfg := &container.Config{
		Image:      r.Image,
		User:       "0:0",
		Entrypoint: []string{"/bin/sh", "-lc"},
		Cmd:        []string{installScript},
		Env:        env(r.Environment),
		WorkingDir: "/",
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
		inspect, inspectErr := m.cli.ContainerInspect(ctx, created.ID)
		if inspectErr != nil {
			return fmt.Errorf("inspect template installer: %w", inspectErr)
		}
		if !inspect.State.Running {
			captured, _ := m.installerLogs(context.Background(), created.ID, "all")
			if captured != "" {
				appendInstallLog(root, captured)
			}
			if inspect.State.ExitCode != 0 {
				return installerError(inspect.State.ExitCode, captured)
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

// ReinstallWithProgress performs the same managed-template reinstall as
// Reinstall, but exposes every important stage through the server console.
func (m *Manager) ReinstallWithProgress(ctx context.Context, r server.CreateRequest) error {
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

	resetInstallLog(root)
	appendInstallLog(root, "container@nodexa~ Server marked as installing...")
	appendInstallLog(root, "[Nodexa Installer] Preparing managed-template reinstall")
	appendInstallLog(root, "[Nodexa Installer] Checking Docker image: "+r.Image)
	if err := m.ensureImage(ctx, r.Image); err != nil {
		appendInstallLog(root, "[Nodexa Installer] ERROR: "+err.Error())
		return err
	}
	appendInstallLog(root, "[Nodexa Installer] Docker image is ready")

	name := "nx-" + r.ID
	if inspect, inspectErr := m.cli.ContainerInspect(ctx, name); inspectErr == nil {
		if inspect.State.Running {
			appendInstallLog(root, "[Nodexa Installer] Stopping running game server")
			t := 10
			_ = m.cli.ContainerStop(ctx, name, container.StopOptions{Timeout: &t})
		}
		appendInstallLog(root, "[Nodexa Installer] Removing old runtime container; server files are preserved")
		if err := m.cli.ContainerRemove(ctx, name, container.RemoveOptions{Force: true}); err != nil {
			appendInstallLog(root, "[Nodexa Installer] ERROR: "+err.Error())
			return fmt.Errorf("remove old container before reinstall: %w", err)
		}
	} else if !errdefs.IsNotFound(inspectErr) {
		appendInstallLog(root, "[Nodexa Installer] ERROR: "+inspectErr.Error())
		return fmt.Errorf("inspect server before reinstall: %w", inspectErr)
	}

	appendInstallLog(root, "[Nodexa Installer] Starting Egg/template installer")
	if err := m.installTemplateWithProgress(ctx, r, root); err != nil {
		appendInstallLog(root, "[Nodexa Installer] INSTALLATION FAILED: "+err.Error())
		return err
	}

	appendInstallLog(root, "[Nodexa Installer] Recreating game-server container")
	if err := m.createContainer(ctx, r, root); err != nil {
		appendInstallLog(root, "[Nodexa Installer] ERROR: "+err.Error())
		return err
	}
	appendInstallLog(root, "container@nodexa~ Reinstall finished. Server is ready to start.")
	return nil
}

var _ io.Reader
