package server

type CreateRequest struct {
	ID          string            `json:"id" binding:"required"`
	Name        string            `json:"name" binding:"required"`
	Image       string            `json:"image" binding:"required"`
	Startup     string            `json:"startup" binding:"required"`
	MemoryMB    int64             `json:"memory_mb"`
	DiskMB      int64             `json:"disk_mb"`
	CPULimit    int64             `json:"cpu_limit"`
	Environment map[string]string `json:"environment"`
}
