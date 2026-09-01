package server

import (
	"bytes"
	"encoding/json"
	"fmt"
	"strings"
)

type CreateRequest struct {
	ID          string            `json:"id" binding:"required"`
	Name        string            `json:"name" binding:"required"`
	Image       string            `json:"image" binding:"required"`
	Startup     string            `json:"startup" binding:"required"`
	Template    string            `json:"template"`
	MemoryMB    int64             `json:"memory_mb"`
	DiskMB      int64             `json:"disk_mb"`
	CPULimit    int64             `json:"cpu_limit"`
	Environment map[string]string `json:"environment"`
}

func (r *CreateRequest) UnmarshalJSON(data []byte) error {
	type wireRequest struct {
		ID          string          `json:"id"`
		Name        string          `json:"name"`
		Image       string          `json:"image"`
		Startup     string          `json:"startup"`
		Template    string          `json:"template"`
		MemoryMB    int64           `json:"memory_mb"`
		DiskMB      int64           `json:"disk_mb"`
		CPULimit    int64           `json:"cpu_limit"`
		Environment json.RawMessage `json:"environment"`
	}

	var w wireRequest
	if err := json.Unmarshal(data, &w); err != nil {
		return err
	}

	r.ID = w.ID
	r.Name = w.Name
	r.Image = w.Image
	r.Startup = w.Startup
	r.Template = strings.TrimSpace(w.Template)
	if r.Template == "" {
		r.Template = "custom"
	}
	r.MemoryMB = w.MemoryMB
	r.DiskMB = w.DiskMB
	r.CPULimit = w.CPULimit
	r.Environment = map[string]string{}

	raw := bytes.TrimSpace(w.Environment)
	if len(raw) == 0 || bytes.Equal(raw, []byte("null")) {
		return nil
	}

	if raw[0] == '{' {
		var values map[string]any
		if err := json.Unmarshal(raw, &values); err != nil {
			return fmt.Errorf("invalid environment object: %w", err)
		}
		for key, value := range values {
			key = strings.TrimSpace(key)
			if key == "" || value == nil {
				continue
			}
			r.Environment[key] = fmt.Sprint(value)
		}
		return nil
	}

	if raw[0] == '[' {
		var values []any
		if err := json.Unmarshal(raw, &values); err != nil {
			return fmt.Errorf("invalid environment array: %w", err)
		}
		for _, value := range values {
			switch item := value.(type) {
			case string:
				key, val, ok := strings.Cut(item, "=")
				key = strings.TrimSpace(key)
				if ok && key != "" {
					r.Environment[key] = val
				}
			case map[string]any:
				for key, val := range item {
					key = strings.TrimSpace(key)
					if key != "" && val != nil {
						r.Environment[key] = fmt.Sprint(val)
					}
				}
			}
		}
		return nil
	}

	return fmt.Errorf("environment must be a JSON object or array")
}
