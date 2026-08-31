package api

import (
	"bufio"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	gpDocker "nodexa/agent/internal/docker"
	"nodexa/agent/internal/server"
	"github.com/gin-gonic/gin"
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
	if c.ShouldBindJSON(&q) != nil {
		c.JSON(422, gin.H{"error": "invalid request"})
		return
	}
	if e := a.docker.Create(c, q); e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(201, gin.H{"id": q.ID, "status": "created"})
}
func (a *API) power(c *gin.Context) {
	var q struct { Signal string `json:"signal"` }
	_ = c.ShouldBindJSON(&q)
	id := c.Param("id")
	var e error
	switch q.Signal {
	case "start":
		e = a.docker.Start(c, id)
	case "stop":
		e = a.docker.Stop(c, id)
	case "restart":
		e = a.docker.Restart(c, id)
	case "kill":
		e = a.docker.Kill(c, id)
	default:
		c.JSON(422, gin.H{"error": "invalid signal"})
		return
	}
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(200, gin.H{"ok": true})
}
func (a *API) command(c *gin.Context) {
	var q struct { Command string `json:"command"` }
	if c.ShouldBindJSON(&q) != nil {
		c.JSON(422, gin.H{"error": "invalid request"})
		return
	}
	if err := a.docker.Command(c, c.Param("id"), q.Command); err != nil {
		c.JSON(500, gin.H{"error": err.Error()})
		return
	}
	c.JSON(200, gin.H{"ok": true})
}
func (a *API) stats(c *gin.Context) {
	s, e := a.docker.Stats(c, c.Param("id"))
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(200, s)
}
func (a *API) logs(c *gin.Context) {
	r, e := a.docker.Logs(c, c.Param("id"), c.DefaultQuery("tail", "200"))
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	defer r.Close()
	c.Header("Content-Type", "text/plain; charset=utf-8")
	scanner := bufio.NewScanner(r)
	for scanner.Scan() {
		line := scanner.Bytes()
		if len(line) > 8 { line = line[8:] }
		_, _ = c.Writer.Write(append(line, '\n'))
		c.Writer.Flush()
	}
}
func (a *API) listFiles(c *gin.Context) {
	p, e := a.docker.SafePath(c.Param("id"), c.DefaultQuery("path", "/"))
	if e != nil {
		c.JSON(400, gin.H{"error": e.Error()})
		return
	}
	items, e := os.ReadDir(p)
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	out := make([]gin.H, 0, len(items))
	for _, it := range items {
		info, _ := it.Info()
		out = append(out, gin.H{"name": it.Name(), "directory": it.IsDir(), "size": info.Size(), "modified_at": info.ModTime()})
	}
	c.JSON(200, gin.H{"items": out})
}
func (a *API) readFile(c *gin.Context) {
	p, e := a.docker.SafePath(c.Param("id"), c.Query("path"))
	if e != nil {
		c.JSON(400, gin.H{"error": e.Error()})
		return
	}
	b, e := os.ReadFile(p)
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.Data(200, "text/plain; charset=utf-8", b)
}
func (a *API) writeFile(c *gin.Context) {
	p, e := a.docker.SafePath(c.Param("id"), c.Query("path"))
	if e != nil {
		c.JSON(400, gin.H{"error": e.Error()})
		return
	}
	b, e := io.ReadAll(io.LimitReader(c.Request.Body, 8*1024*1024))
	if e != nil {
		c.JSON(400, gin.H{"error": e.Error()})
		return
	}
	if e = os.MkdirAll(filepath.Dir(p), 0750); e == nil { e = os.WriteFile(p, b, 0640) }
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(200, gin.H{"ok": true, "bytes": len(b)})
}
func (a *API) makeDirectory(c *gin.Context) {
	var q struct { Path string `json:"path"` }
	if c.ShouldBindJSON(&q) != nil {
		c.JSON(422, gin.H{"error": "invalid request"})
		return
	}
	p, e := a.docker.SafePath(c.Param("id"), q.Path)
	if e == nil { e = os.MkdirAll(p, 0750) }
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(201, gin.H{"ok": true})
}
func (a *API) deleteFile(c *gin.Context) {
	p, e := a.docker.SafePath(c.Param("id"), c.Query("path"))
	if e == nil { e = os.RemoveAll(p) }
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	c.JSON(200, gin.H{"ok": true})
}
func (a *API) backup(c *gin.Context) {
	var q struct { Name string `json:"name"` }
	_ = c.ShouldBindJSON(&q)
	p, e := a.docker.Backup(c.Param("id"), q.Name)
	if e != nil {
		c.JSON(500, gin.H{"error": e.Error()})
		return
	}
	info, _ := os.Stat(p)
	c.JSON(201, gin.H{"ok": true, "name": filepath.Base(p), "bytes": info.Size()})
}

var _ = http.StatusOK
var _ = strconv.Itoa
var _ = strings.TrimSpace
