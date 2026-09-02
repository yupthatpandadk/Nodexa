package health

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestCollectReportsAgentAndHostMetrics(t *testing.T) {
	t.Setenv("NODEXA_AGENT_VERSION", "test-version")
	payload := Collect(t.TempDir())
	if !payload.OK || payload.Service != "nodexa-agent" {
		t.Fatalf("unexpected health identity: %#v", payload)
	}
	if payload.APIVersion != 1 {
		t.Fatalf("expected API v1, got %d", payload.APIVersion)
	}
	if payload.Version != "test-version" {
		t.Fatalf("expected injected version, got %q", payload.Version)
	}
	if payload.System.CPUCount < 1 {
		t.Fatalf("expected at least one CPU, got %d", payload.System.CPUCount)
	}
	if payload.System.MemoryTotalBytes == 0 {
		t.Fatal("expected host memory metrics")
	}
	if payload.System.DiskTotalBytes == 0 {
		t.Fatal("expected host disk metrics")
	}
}

func TestHandlerReturnsJSON(t *testing.T) {
	t.Setenv("NODEXA_AGENT_VERSION", "test-version")
	recorder := httptest.NewRecorder()
	request := httptest.NewRequest(http.MethodGet, "/health", nil)
	Handler(t.TempDir()).ServeHTTP(recorder, request)
	if recorder.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", recorder.Code)
	}
	var payload Payload
	if err := json.Unmarshal(recorder.Body.Bytes(), &payload); err != nil {
		t.Fatalf("invalid JSON: %v", err)
	}
	if payload.Service != "nodexa-agent" || payload.Version != "test-version" {
		t.Fatalf("unexpected payload: %#v", payload)
	}
}
