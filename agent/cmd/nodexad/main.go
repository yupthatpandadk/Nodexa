package main

import (
	"nodexa/agent/internal/api"
	"nodexa/agent/internal/config"
	gpDocker "nodexa/agent/internal/docker"
	"log"
)

func main() {
	c := config.Load()
	if c.Token == "" {
		log.Fatal("NODEXA_TOKEN is required")
	}
	d, e := gpDocker.New(c.DataRoot)
	if e != nil {
		log.Fatal(e)
	}
	log.Printf("Nodexa Agent listening on %s", c.Listen)
	log.Fatal(api.New(c.Token, d).Router().Run(c.Listen))
}
