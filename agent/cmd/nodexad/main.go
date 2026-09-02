package main

import (
	"log"

	"nodexa/agent/internal/api"
	"nodexa/agent/internal/config"
	gpDocker "nodexa/agent/internal/docker"
	nodexaSFTP "nodexa/agent/internal/sftp"
)

func main() {
	c := config.Load()
	if c.Token == "" { log.Fatal("NODEXA_TOKEN is required") }
	d, err := gpDocker.New(c.DataRoot)
	if err != nil { log.Fatal(err) }

	resolver := nodexaSFTP.NewFileResolver(c.SFTPCredentials)
	sftpServer := &nodexaSFTP.Server{
		Listen: c.SFTPListen,
		DataRoot: c.DataRoot,
		HostKeyPath: c.SFTPHostKey,
		Resolver: resolver,
	}
	go func() {
		if err := sftpServer.Run(); err != nil { log.Fatalf("Nodexa SFTP stopped: %v", err) }
	}()

	log.Printf("Nodexa Agent listening on %s", c.Listen)
	log.Fatal(api.New(c.Token, d).Router().Run(c.Listen))
}
