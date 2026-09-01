package api

import (
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	gpDocker "nodexa/agent/internal/docker"
	"nodexa/agent/internal/server"
)

type API struct {
	token  string
	docker *gpDocker.Manager
}

func New(token string, d *gpDocker.Manager) *API { return &API{token, d} }

func (a *API) Router() *gin.Engine {
	r := gin.New()
	r.Use(gin.Recovery())
	r.GET("/health", func(c *gin.Context) { c.JSON(200, gin.H{"ok": true, "service": "nodexa-agent"}) })
	api := r.Group("/api", a.auth)
	api.POST("/servers", a.create)
	api.POST("/servers/:id/reinstall", a.reinstall)
	api.POST("/servers/:id/power", a.power)
	api.POST("/servers/:id/command", a.command)
	api.GET("/servers/:id/stats", a.stats)
	api.GET("/servers/:id/logs", a.logs)
	api.GET("/servers/:id/files", a.listFiles)
	api.GET("/servers/:id/files/content", a.readFile)
	api.PUT("/servers/:id/files/content", a.writeFile)
	api.POST("/servers/:id/files/directory", a.makeDirectory)
	api.DELETE("/servers/:id/files", a.deleteFile)
	api.POST("/servers/:id/backups", a.backup)
	return r
}

func (a *API) auth(c *gin.Context) {
	if c.GetHeader("Authorization") != "Bearer "+a.token {
		c.AbortWithStatusJSON(401, gin.H{"error": "unauthorized"})
		return
	}
	c.Next()
}

func (a *API) create(c *gin.Context) {
	var q server.CreateRequest
	if err := c.ShouldBindJSON(&q); err != nil {
		c.JSON(422, gin.H{"error": "invalid request", "detail": err.Error()})
		return
	}
	if err := a.docker.Create(c, q); err != nil {
		c.JSON(500, gin.H{"error": err.Error()})
		return
	}
	c.JSON(201, gin.H{"id": q.ID, "status": "created"})
}

func (a *API) reinstall(c *gin.Context) {
	var q server.CreateRequest
	if err := c.ShouldBindJSON(&q); err != nil {
		c.JSON(422, gin.H{"error": "invalid request", "detail": err.Error()})
		return
	}
	q.ID = c.Param("id")
	if err := a.docker.ReinstallWithProgress(c, q); err != nil {
		c.JSON(500, gin.H{"error": err.Error()})
		return
	}
	c.JSON(200, gin.H{"id": q.ID, "status": "reinstalled"})
}

func (a *API) power(c *gin.Context) {
	var q struct { Signal string `json:"signal"` }
	if err := c.ShouldBindJSON(&q); err != nil { c.JSON(422, gin.H{"error": "invalid request"}); return }
	id := c.Param("id")
	var err error
	switch q.Signal {
	case "start": err = a.docker.Start(c, id)
	case "stop": err = a.docker.Stop(c, id)
	case "restart": err = a.docker.Restart(c, id)
	case "kill": err = a.docker.Kill(c, id)
	default: c.JSON(422, gin.H{"error": "invalid signal"}); return
	}
	if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	c.JSON(200, gin.H{"ok": true})
}

func (a *API) command(c *gin.Context) {
	var q struct { Command string `json:"command"` }
	if err := c.ShouldBindJSON(&q); err != nil { c.JSON(422, gin.H{"error": "invalid request"}); return }
	if err := a.docker.Command(c, c.Param("id"), q.Command); err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	c.JSON(200, gin.H{"ok": true})
}

func (a *API) stats(c *gin.Context) {
	stats, err := a.docker.Stats(c, c.Param("id")); if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }; c.JSON(200, stats)
}

func (a *API) logs(c *gin.Context) {
	id := c.Param("id")
	tail := c.DefaultQuery("tail", "200")

	// Managed-template installation output takes over the normal console while
	// a reinstall is running. Once the game container starts, normal runtime
	// logs automatically become visible again.
	if output, handled, err := a.docker.InstallConsole(c, id, tail); err != nil {
		c.JSON(500, gin.H{"error": err.Error()})
		return
	} else if handled {
		c.Data(200, "text/plain; charset=utf-8", []byte(output+"\n"))
		return
	}

	reader, err := a.docker.Logs(c, id, tail)
	if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	defer reader.Close()
	body, err := io.ReadAll(io.LimitReader(reader, 2*1024*1024))
	if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	// Server containers use a TTY, therefore Docker returns plain console text.
	// Do not strip the first eight bytes from each line; doing so previously
	// truncated timestamps and the beginning of Minecraft/FiveM messages.
	c.Data(200, "text/plain; charset=utf-8", body)
}

func (a *API) listFiles(c *gin.Context) {
	path, err := a.docker.SafePath(c.Param("id"), c.DefaultQuery("path", "/")); if err != nil { c.JSON(400, gin.H{"error": err.Error()}); return }
	items, err := os.ReadDir(path); if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	out := make([]gin.H, 0, len(items))
	for _, item := range items { info, infoErr := item.Info(); if infoErr != nil { continue }; out = append(out, gin.H{"name": item.Name(), "directory": item.IsDir(), "size": info.Size(), "modified_at": info.ModTime()}) }
	c.JSON(200, gin.H{"items": out})
}

func (a *API) readFile(c *gin.Context) {
	path, err := a.docker.SafePath(c.Param("id"), c.Query("path")); if err != nil { c.JSON(400, gin.H{"error": err.Error()}); return }
	body, err := os.ReadFile(path); if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }; c.Data(200, "text/plain; charset=utf-8", body)
}

func (a *API) writeFile(c *gin.Context) {
	path, err := a.docker.SafePath(c.Param("id"), c.Query("path")); if err != nil { c.JSON(400, gin.H{"error": err.Error()}); return }
	body, err := io.ReadAll(io.LimitReader(c.Request.Body, 8*1024*1024)); if err != nil { c.JSON(400, gin.H{"error": err.Error()}); return }
	if err = os.MkdirAll(filepath.Dir(path), 0750); err == nil { err = os.WriteFile(path, body, 0640) }
	if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }; c.JSON(200, gin.H{"ok": true, "bytes": len(body)})
}

func (a *API) makeDirectory(c *gin.Context) {
	var q struct { Path string `json:"path"` }; if err := c.ShouldBindJSON(&q); err != nil { c.JSON(422, gin.H{"error": "invalid request"}); return }
	path, err := a.docker.SafePath(c.Param("id"), q.Path); if err == nil { err = os.MkdirAll(path, 0750) }; if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }; c.JSON(201, gin.H{"ok": true})
}

func (a *API) deleteFile(c *gin.Context) {
	path, err := a.docker.SafePath(c.Param("id"), c.Query("path")); if err == nil { err = os.RemoveAll(path) }; if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }; c.JSON(200, gin.H{"ok": true})
}

func (a *API) backup(c *gin.Context) {
	var q struct { Name string `json:"name"` }; if err := c.ShouldBindJSON(&q); err != nil { c.JSON(422, gin.H{"error": "invalid request"}); return }
	path, err := a.docker.Backup(c.Param("id"), q.Name); if err != nil { c.JSON(500, gin.H{"error": err.Error()}); return }
	info, err := os.Stat(path); if err != nil { c.JSON(500, gin.H{"error": "backup was created but could not be inspected: " + err.Error()}); return }
	c.JSON(201, gin.H{"ok": true, "name": filepath.Base(path), "bytes": info.Size()})
}

var _ = http.StatusOK
var _ = strconv.Itoa
var _ = strings.TrimSpace
