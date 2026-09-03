package docker

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/docker/docker/api/types/container"
)

func TestValidateManagedRuntime(t *testing.T) {
	m := &Manager{dataRoot: t.TempDir()}
	id := "server-test"
	root, err := m.serverRoot(id)
	if err != nil { t.Fatal(err) }
	if err := os.MkdirAll(root, 0750); err != nil { t.Fatal(err) }
	if err := m.validateManagedRuntime(id, "custom"); err != nil { t.Fatalf("custom template should not require Minecraft files: %v", err) }
	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil || !strings.Contains(err.Error(), "installation marker is missing") { t.Fatalf("expected missing marker error, got %v", err) }
	if err := os.WriteFile(filepath.Join(root, ".nodexa-installed"), []byte("ok\n"), 0640); err != nil { t.Fatal(err) }
	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil || !strings.Contains(err.Error(), "server.jar is missing") { t.Fatalf("expected missing jar error, got %v", err) }
	if err := os.WriteFile(filepath.Join(root, "server.jar"), []byte("not-a-jar"), 0640); err != nil { t.Fatal(err) }
	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil { t.Fatal("expected invalid jar error") }
	validJar := make([]byte, 2048);validJar[0]='P';validJar[1]='K'
	if err := os.WriteFile(filepath.Join(root, "server.jar"), validJar, 0640); err != nil { t.Fatal(err) }
	if err := m.validateManagedRuntime(id, "minecraft-java"); err != nil { t.Fatalf("expected valid managed runtime, got %v", err) }
}

func TestRuntimeReferencesServerJarForLegacyContainers(t *testing.T) {
	if runtimeReferencesServerJar(nil) { t.Fatal("nil config must not require server.jar") }
	if runtimeReferencesServerJar(&container.Config{Cmd: []string{"/bin/sh", "-lc", "node index.js"}}) { t.Fatal("non-Minecraft startup must not require server.jar") }
	if !runtimeReferencesServerJar(&container.Config{Cmd: []string{"/bin/sh", "-lc", "java -Xmx4G -jar server.jar nogui"}}) { t.Fatal("legacy Minecraft startup referencing server.jar must be detected") }
	if !runtimeReferencesServerJar(&container.Config{Entrypoint: []string{"java"}, Cmd: []string{"-jar", "server.jar", "nogui"}}) { t.Fatal("server.jar in entrypoint/cmd combination must be detected") }
	if !runtimeReferencesServerJar(&container.Config{Cmd: []string{"/bin/sh", "-lc", `java -jar "${SERVER_JARFILE:-server.jar}" nogui`}}) { t.Fatal("template startup using SERVER_JARFILE must be detected") }
}

func TestValidateServerJarWithoutTemplateMarker(t *testing.T) {
	m := &Manager{dataRoot: t.TempDir()}
	id := "legacy-server"
	root, err := m.serverRoot(id)
	if err != nil { t.Fatal(err) }
	if err := os.MkdirAll(root, 0750); err != nil { t.Fatal(err) }
	if err := m.validateServerJar(id); err == nil || !strings.Contains(err.Error(), "server.jar is missing") { t.Fatalf("expected missing legacy jar error, got %v", err) }
	validJar := make([]byte, 2048);validJar[0]='P';validJar[1]='K'
	if err := os.WriteFile(filepath.Join(root, "server.jar"), validJar, 0640); err != nil { t.Fatal(err) }
	if err := m.validateServerJar(id); err != nil { t.Fatalf("legacy runtime with a valid server.jar should pass: %v", err) }
}

func TestConfiguredServerJarFilename(t *testing.T) {
	m := &Manager{dataRoot: t.TempDir()}
	id := "custom-jar-server"
	root, err := m.serverRoot(id)
	if err != nil { t.Fatal(err) }
	if err := os.MkdirAll(root, 0750); err != nil { t.Fatal(err) }
	if err := os.WriteFile(filepath.Join(root, ".nodexa-installed"), []byte("ok\n"), 0640); err != nil { t.Fatal(err) }
	validJar := make([]byte, 2048);validJar[0]='P';validJar[1]='K'
	if err := os.WriteFile(filepath.Join(root, "paper.jar"), validJar, 0640); err != nil { t.Fatal(err) }
	if err := m.validateManagedRuntimeNamed(id, "minecraft-java", "paper.jar"); err != nil { t.Fatalf("configured paper.jar should validate: %v", err) }
	cfg := &container.Config{Env: []string{"SERVER_JARFILE=paper.jar"}, Cmd: []string{"/bin/sh", "-lc", `java -jar "${SERVER_JARFILE:-server.jar}" nogui`}}
	if got := runtimeServerJarName(cfg); got != "paper.jar" { t.Fatalf("expected configured jar name, got %q", got) }
	if err := validateJarFilename("../escape.jar"); err == nil { t.Fatal("path traversal jar name should be rejected") }
}
