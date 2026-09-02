package sftp

import (
    "encoding/json"
    "errors"
    "os"
    "path/filepath"
    "sync"
)

type CredentialStore struct { Path string; mu sync.Mutex }

func (s *CredentialStore) Upsert(c Credential) error {
    if c.Username == "" || c.Password == "" || c.ServerUUID == "" { return errors.New("username, password and server UUID are required") }
    s.mu.Lock(); defer s.mu.Unlock()
    list, err := s.read(); if err != nil { return err }
    replaced := false
    for i := range list {
        if list[i].Username == c.Username || list[i].ServerUUID == c.ServerUUID { list[i] = c; replaced = true; break }
    }
    if !replaced { list = append(list, c) }
    return s.write(list)
}

func (s *CredentialStore) DeleteServer(serverUUID string) error {
    s.mu.Lock(); defer s.mu.Unlock()
    list, err := s.read(); if err != nil { return err }
    out := list[:0]
    for _, c := range list { if c.ServerUUID != serverUUID { out = append(out, c) } }
    return s.write(out)
}

func (s *CredentialStore) read() ([]Credential, error) {
    b, err := os.ReadFile(s.Path)
    if os.IsNotExist(err) { return []Credential{}, nil }
    if err != nil { return nil, err }
    var list []Credential
    if len(b) > 0 { if err := json.Unmarshal(b, &list); err != nil { return nil, err } }
    return list, nil
}

func (s *CredentialStore) write(list []Credential) error {
    if err := os.MkdirAll(filepath.Dir(s.Path), 0700); err != nil { return err }
    b, err := json.Marshal(list); if err != nil { return err }
    tmp := s.Path + ".tmp"
    if err := os.WriteFile(tmp, b, 0600); err != nil { return err }
    return os.Rename(tmp, s.Path)
}
