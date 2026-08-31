package config

import "os"

type Config struct {
	Listen   string
	Token    string
	DataRoot string
}

func Load() Config {
	c := Config{Listen: ":8080", Token: os.Getenv("NODEXA_TOKEN"), DataRoot: "/var/lib/nodexa/servers"}
	if v := os.Getenv("NODEXA_LISTEN"); v != "" {
		c.Listen = v
	}
	if v := os.Getenv("NODEXA_DATA"); v != "" {
		c.DataRoot = v
	}
	return c
}
