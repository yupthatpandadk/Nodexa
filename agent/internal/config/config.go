package config

import "os"

type Config struct {
	Listen string
	Token string
	DataRoot string
	SFTPListen string
	SFTPHostKey string
	SFTPCredentials string
}

func Load() Config {
	c := Config{
		Listen: ":8080", Token: os.Getenv("NODEXA_TOKEN"), DataRoot: "/var/lib/nodexa/servers",
		SFTPListen: ":2022", SFTPHostKey: "/var/lib/nodexa/sftp_host_ed25519",
		SFTPCredentials: "/var/lib/nodexa/sftp_credentials.json",
	}
	if v := os.Getenv("NODEXA_LISTEN"); v != "" { c.Listen = v } else if v := os.Getenv("NODEXA_ADDR"); v != "" { c.Listen = v }
	if v := os.Getenv("NODEXA_DATA"); v != "" { c.DataRoot = v }
	if v := os.Getenv("NODEXA_SFTP_LISTEN"); v != "" { c.SFTPListen = v }
	if v := os.Getenv("NODEXA_SFTP_HOST_KEY"); v != "" { c.SFTPHostKey = v }
	if v := os.Getenv("NODEXA_SFTP_CREDENTIALS"); v != "" { c.SFTPCredentials = v }
	return c
}
