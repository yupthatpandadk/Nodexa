package docker

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestValidateManagedRuntime(t *testing.T) {
	m := &Manager{dataRoot: t.TempDir()}
	id := "server-test"
	root, err := m.serverRoot(id)
	if err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(root, 0750); err != nil {
		t.Fatal(err)
	}

	if err := m.validateManagedRuntime(id, "custom"); err != nil {
		t.Fatalf("custom template should not require Minecraft files: %v", err)
	}

	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil || !strings.Contains(err.Error(), "installation marker is missing") {
		t.Fatalf("expected missing marker error, got %v", err)
	}

	if err := os.WriteFile(filepath.Join(root, ".nodexa-installed"), []byte("ok\n"), 0640); err != nil {
		t.Fatal(err)
	}
	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil || !strings.Contains(err.Error(), "server.jar is missing") {
		t.Fatalf("expected missing jar error, got %v", err)
	}

	if err := os.WriteFile(filepath.Join(root, "server.jar"), []byte("not-a-jar"), 0640); err != nil {
		t.Fatal(err)
	}
	if err := m.validateManagedRuntime(id, "minecraft-java"); err == nil {
		t.Fatal("expected invalid jar error")
	}

	validJar := make([]byte, 2048)
	validJar[0] = 'P'
	validJar[1] = 'K'
	if err := os.WriteFile(filepath.Join(root, "server.jar"), validJar, 0640); err != nil {
		t.Fatal(err)
	}
	if err := m.validateManagedRuntime(id, "minecraft-java"); err != nil {
		t.Fatalf("expected valid managed runtime, got %v", err)
	}
}
