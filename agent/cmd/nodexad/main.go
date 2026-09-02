package main

import (
	"log"
	"net/http"

	"nodexa/agent/internal/api"
	"nodexa/agent/internal/config"
	gpDocker "nodexa/agent/internal/docker"
	"nodexa/agent/internal/health"
	nodexaSFTP "nodexa/agent/internal/sftp"
)

func main() {
	c := config.Load()
	if c.Token == "" { log.Fatal("NODEXA_TOKEN is required") }
	d, err := gpDocker.New(c.DataRoot)
	if err != nil { log.Fatal(err) }

	resolver := nodexaSFTP.NewFileResolver(c.SFTPCredentials)
	store := &nodexaSFTP.CredentialStore{Path: c.SFTPCredentials}
	sftpServer := &nodexaSFTP.Server{Listen:c.SFTPListen,DataRoot:c.DataRoot,HostKeyPath:c.SFTPHostKey,Resolver:resolver}
	go func() { if err := sftpServer.Run(); err != nil { log.Fatalf("Nodexa SFTP stopped: %v", err) } }()

	router := api.New(c.Token, d, store).Router()
	healthHandler := health.Handler(c.DataRoot)
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet && r.URL.Path == "/health" {
			healthHandler.ServeHTTP(w, r)
			return
		}
		router.ServeHTTP(w, r)
	})

	log.Printf("Nodexa Agent listening on %s", c.Listen)
	log.Fatal(http.ListenAndServe(c.Listen, handler))
}
